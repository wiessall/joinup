<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Plugin\migrate\process;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\import_ird_jsons\Resolver\CandidateIndex;
use Drupal\import_ird_jsons\Resolver\EntityCandidates;
use Drupal\import_ird_jsons\Resolver\LlmJudge;
use Drupal\import_ird_jsons\Resolver\MatchResult;
use Drupal\import_ird_jsons\Resolver\ResolutionStore;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Finds an entity by name, tolerating typos, case, accents, acronyms, codes and qualifiers; optionally asks an LLM.
 *
 * A drop-in for entity_lookup / entity_generate where names are messy. It returns an entity id or NULL.
 * Only entity_type and bundle are required:
 *
 * @code
 * country:
 *   plugin: entity_fuzzy_lookup
 *   source: country
 *   entity_type: taxonomy_term
 *   bundle: spatial
 * @endcode
 *
 * Everything else has a default, shown here; fields an entity lacks are skipped:
 *
 * @code
 *   name_fields: [<label>, variant, alt_labels, synonyms, aliases]   # also with a field_ prefix
 *   acronym_fields: [acronym, field_acronym]
 *   code_fields: [code, iso_code, iso_code_2, m49_code]   # matched exactly, as typed in capitals ("KE", "KEN")
 *   kind: country         # for the LLM's questions; "country" when the entities have codes, else "organisation"
 *   threshold: 0.9        # fuzzy: minimum similarity
 *   margin: 0.05          # fuzzy: lead over the second best
 *   llm_model: 'qwen2.5:7b'               # optional: every name not matched exactly goes to Ollama, which gives
 *                                         # other names (English, own language, acronym, former) to look up and
 *                                         # then chooses among the candidates found, or none
 *   llm_url: 'http://ollama:11434'
 *   llm_think: false      # optional: Ollama's think setting; false makes thinking models (qwen3) answer directly
 *   llm_timeout: 120      # seconds per call
 *   on_miss: null         # or 'generate': create an entity with this name (and default_values)
 *   default_values:
 *     field_state: published
 * @endcode
 *
 * Every non-exact outcome is logged as a migrate message (drush migrate:messages) and remembered in
 * Drupal's key-value store (see ResolutionStore), so re-imports resolve each name the same way.
 */
#[MigrateProcess(id: 'entity_fuzzy_lookup')]
final class EntityFuzzyLookup extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  private ?CandidateIndex $index = NULL;

  /** @var array<string, string> */
  private array $labels = [];

  private bool $hasCodes = FALSE;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ResolutionStore $store,
    private readonly ClientInterface $http,
    private readonly EntityCandidates $candidates,
  ) {
    foreach (['entity_type', 'bundle'] as $key) {
      if (empty($configuration[$key])) {
        throw new \InvalidArgumentException("entity_fuzzy_lookup: '$key' is required.");
      }
    }
    $configuration += EntityCandidates::defaults($entityTypeManager->getDefinition($configuration['entity_type'])) + [
      'kind' => NULL,
      'llm_model' => NULL,
      'llm_url' => 'http://ollama:11434',
      'llm_think' => NULL,
      'llm_timeout' => 120,
      'on_miss' => NULL,
      'default_values' => [],
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      new ResolutionStore($container->get('keyvalue')),
      $container->get('http_client'),
      $container->get('import_ird_jsons.entity_candidates'),
    );
  }

  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): ?string {
    $input = trim((string) $value);
    if ($input === '') {
      return NULL;
    }
    $scope = $this->configuration['entity_type'] . ':' . $this->configuration['bundle'];
    $log = fn(string $message) => $migrate_executable->saveMessage(
      "$destination_property: \"$input\" $message", MigrationInterface::MESSAGE_INFORMATIONAL);

    // 1. A remembered match wins, as long as its target still exists. A remembered "none" only saves asking the
    //    LLM again: an exact match to an entity added since still counts. A deleted match is resolved again, but
    //    a deleted stub this plugin generated was rejected by a curator: that is remembered, so it is not
    //    generated again on the next import.
    $stored = $this->store->get($scope, $input);
    if ($stored && $stored['target'] !== NULL) {
      if ($this->storage()->load($stored['target'])) {
        return $stored['target'];
      }
      if ($stored['tier'] === 'generated') {
        $this->store->set($scope, $input, NULL, 'deleted', 0.0);
        $stored = ['target' => NULL, 'tier' => 'deleted'];
      }
      else {
        $this->store->delete($scope, $input);
        $stored = NULL;
      }
    }

    // 2. Deterministic tiers.
    $result = $this->index()->resolve($input);
    if ($result->id !== NULL) {
      if ($result->tier !== MatchResult::KEY) {
        $this->store->set($scope, $input, $result->id, $result->tier, $result->score);
        $log(sprintf('matched by %s (%.2f) to %s "%s"', $result->tier, $result->score, $result->id, $this->labels[$result->id] ?? ''));
      }
      return $result->id;
    }
    if (($stored['tier'] ?? NULL) === 'deleted') {
      $log('not found; its generated entity was deleted, so it is not created again');
      return NULL;
    }

    // 3. Everything not matched goes to the LLM, close candidates or not. It first gives the other names it knows
    //    (English, original language, acronym, former name), each looked up like the input: that is how a
    //    translation or a renamed organisation finds its candidates. It then chooses among all candidates, or
    //    says none. Its answer, "none" too, is remembered, so each name is asked about once. A failed call is not
    //    an answer: it is logged and not remembered, so the next run asks again.
    $note = $stored !== NULL ? 'LLM said none in an earlier run' : '';
    if ($this->configuration['llm_model'] && $stored === NULL) {
      $judge = new LlmJudge($this->http, $this->configuration['llm_url'], $this->configuration['llm_model'],
        $this->configuration['kind'] ?? ($this->hasCodes ? 'country' : 'organisation'),
        (int) $this->configuration['llm_timeout'], $this->configuration['llm_think']);
      try {
        $scores = $result->candidates;
        $otherNames = $judge->otherNames($input);
        foreach ($otherNames as $name) {
          $more = $this->index()->resolve($name);
          foreach ($more->id !== NULL ? [$more->id => 1.0] : $more->candidates as $id => $score) {
            $scores[$id] = max($scores[$id] ?? 0.0, $score);
          }
        }
        arsort($scores);
        $candidates = array_intersect_key($this->labels, array_slice($scores, 0, 10, TRUE));
        $choice = $judge->choose($input, $candidates, $otherNames);
        $this->store->set($scope, $input, $choice, 'llm', $choice === NULL ? 0.0 : $scores[$choice]);
        if ($choice !== NULL) {
          $log(sprintf('matched by LLM to %s "%s" (also known as: %s; candidates: %s)', $choice, $this->labels[$choice],
            implode('; ', $otherNames) ?: '-', implode(', ', array_keys($candidates))));
          return $choice;
        }
        $note = $candidates
          ? sprintf('LLM said none (also known as: %s; candidates: %s)', implode('; ', $otherNames) ?: '-',
            implode(', ', array_keys($candidates)))
          : sprintf('no candidates, not even through the names the LLM knows it by (%s)', implode('; ', $otherNames) ?: 'none given');
      }
      catch (\RuntimeException $e) {
        $note = 'LLM call failed, will ask again next run: ' . $e->getMessage();
      }
    }
    return $this->miss($input, $scope, $log, note: $note, candidates: array_keys($result->candidates));
  }

  /**
   * Nothing matched: generate an entity, or leave the value empty.
   */
  private function miss(string $input, string $scope, \Closure $log, string $note = '', array $candidates = []): ?string {
    if ($this->configuration['on_miss'] !== 'generate') {
      $log('not found' . ($candidates ? ' (close: ' . implode(', ', $candidates) . ')' : '') . ($note !== '' ? "; $note" : ''));
      return NULL;
    }
    $label_key = $this->entityTypeManager->getDefinition($this->configuration['entity_type'])->getKey('label');
    $entity = $this->storage()->create([
      $this->configuration['bundle_key'] => $this->configuration['bundle'],
      $label_key => $input,
    ] + $this->configuration['default_values']);
    $entity->save();
    $id = (string) $entity->id();
    $this->index()->add($id, [$input]);
    $this->labels[$id] = $input;
    $this->store->set($scope, $input, $id, 'generated', 0.0);
    $log("created as $id" . ($candidates ? ' (close: ' . implode(', ', $candidates) . ')' : ''));
    return $id;
  }

  /**
   * Builds the index once per migration run from the existing entities.
   */
  private function index(): CandidateIndex {
    if ($this->index === NULL) {
      ['index' => $this->index, 'labels' => $this->labels, 'hasCodes' => $this->hasCodes] = $this->candidates->build(
        $this->configuration['entity_type'], $this->configuration['bundle'], $this->configuration);
    }
    return $this->index;
  }

  private function storage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage($this->configuration['entity_type']);
  }

}
