<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Plugin\migrate\process;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds reified field-claim paragraphs for a solution row, inline in migrate.
 *
 * This is the migrate-native replacement for the standalone `ird:annotate`
 * Drush command. Given a row's `claims` object and the collection's
 * `field_config`, it mints one `claims` paragraph per asserted value and
 * returns the reference list to be written to the solution's `field_claims`.
 *
 * A claim is a reified statement:
 *   solution  sti:hasFieldClaim  claim
 *     claim   sti:claimProperty     <property-uri>     (predicate)
 *     claim   sti:claimLiteralValue "…"  |  sti:claimRefValue <value-uri> (object)
 *     claim   sti:claimedBy         <collection-nid>   (provenance)
 *     claim   sti:claimedAt         "…"                (provenance)
 *
 * Predicates and categorical values live in two shared hierarchical vocabularies
 * (parent term = collection id):
 *   claim_properties:  {collection} → {key}
 *   claim_values:      {collection} → {key} → {value}
 * Term URIs are minted by rdf_sync itself: claim_properties uses the
 * 'sti_namespace:property' URI plugin and claim_values 'sti_namespace:concept'
 * (https://sti-portal.fao.org/id/{type}/{uuid}). This plugin used to write
 * http://data.fao.org/ird/… URIs into field_property_uri, a field that never
 * existed, so the terms ended up with no URI at all.
 *
 * Idempotency: because this runs during import (and the destination lists
 * `field_claims` in overwrite_properties), on re-import we look up the already
 * migrated node, DELETE this collection's previously created paragraphs, keep
 * every OTHER collection's claim references, and return kept-refs + freshly
 * built refs. First import: no node yet → nothing to clear, returns new refs.
 *
 * Claims whose property is a root-level claim_properties term are kept even
 * when attributed to this collection. This plugin only ever creates property
 * terms under the collection's own term; root-level ones are system
 * predicates written by other modules (sti_dedupe's sameInnovationAs), and
 * deleting them here would silently undo a reviewer's decision.
 *
 * All work is wrapped so a single bad claim/value is logged and skipped rather
 * than failing the whole row — safe for unattended overnight runs.
 *
 * Config (set per migration in YAML):
 *   plugin: ird_claims
 *   collection_id: 'fao-teca'
 *   source: claims
 *   field_config: { <key>: { categorical: bool, separator: '|'? }, … }
 *
 * @\Drupal\migrate\Annotation\MigrateProcessPlugin(
 *   id = "ird_claims",
 *   handle_multiples = TRUE
 * )
 */
class IrdClaims extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  private const VOCAB_PROPERTIES  = 'claim_properties';
  private const VOCAB_VALUES      = 'claim_values';
  private const PARAGRAPH_BUNDLE  = 'claims';
  private const SOLUTION_BUNDLE   = 'solution';
  private const COLLECTION_BUNDLE = 'collection';
  private const MAX_TERM_NAME     = 255;

  /** Per-process static caches (one migrate run reuses them). */
  private array $termCache = [];
  private static array $collectionNidCache = [];

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected TimeInterface $time,
    protected ?MigrationInterface $migration,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $migration,
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param mixed $value
   *   The row's `claims` object: { key: { value: scalar|array }, … }.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property): array {
    $logger = \Drupal::logger('ird_claims');

    $collection_id = (string) ($this->configuration['collection_id'] ?? '');
    $field_config  = (array) ($this->configuration['field_config'] ?? []);
    $source_id     = (string) $row->getSourceProperty('id');

    if ($collection_id === '') {
      $logger->error('ird_claims: missing collection_id in plugin config; skipping claims.');
      return [];
    }

    // Resolve collection nid once per run; a missing collection is fatal for
    // claims but must NOT abort the node import, so log and bail with [].
    try {
      $collection_nid = $this->loadCollectionNid($collection_id);
    }
    catch (\Throwable $e) {
      $logger->error('ird_claims: @m', ['@m' => $e->getMessage()]);
      return [];
    }

    if (!is_array($value) || $value === []) {
      return [];
    }

    // ---- Idempotency: clear this collection's old claims, keep others. ----
    $kept_refs = [];
    $node = $this->lookupMigratedNode($source_id);
    if ($node instanceof NodeInterface && $node->hasField('field_claims')) {
      foreach ($node->get('field_claims') as $item) {
        $paragraph = $item->entity;
        if (!$paragraph instanceof ParagraphInterface) {
          continue;
        }
        $claimed_by = (int) $paragraph->get('field_claimed_by')->target_id;
        if ($claimed_by === $collection_nid && !self::isSystemClaim($paragraph)) {
          try {
            $paragraph->delete();
          }
          catch (\Throwable $e) {
            $logger->warning('ird_claims: failed deleting old paragraph @id: @m',
              ['@id' => $paragraph->id(), '@m' => $e->getMessage()]);
          }
        }
        else {
          $kept_refs[] = [
            'target_id'          => $paragraph->id(),
            'target_revision_id' => $paragraph->getRevisionId(),
          ];
        }
      }
    }

    // ---- Build fresh paragraphs for this collection. ----
    $properties_parent = $this->ensureTerm(self::VOCAB_PROPERTIES, $collection_id);
    $values_parent     = $this->ensureTerm(self::VOCAB_VALUES, $collection_id);

    $new_refs = [];
    foreach ($value as $key => $claim) {
      try {
        $key = (string) $key;
        if (mb_strlen($key) > self::MAX_TERM_NAME) {
          $logger->warning('ird_claims: over-long claim key (@n chars) id=@id: @k…', [
            '@n' => mb_strlen($key), '@id' => $source_id, '@k' => mb_substr($key, 0, 60),
          ]);
          continue;
        }
        $raw_value = is_array($claim) && array_key_exists('value', $claim) ? $claim['value'] : $claim;

        $meta           = $field_config[$key] ?? [];
        $is_categorical = (bool) ($meta['categorical'] ?? FALSE);
        $separator      = $meta['separator'] ?? NULL;
        $property_term  = $this->ensureKeyTerm(self::VOCAB_PROPERTIES, $key, $properties_parent);

        foreach ($this->normalizeValues($raw_value, $separator) as $single) {
          try {
            // A categorical value becomes a term name (varchar 255). Anything
            // longer is almost certainly misclassified free text — skip it.
            if ($is_categorical && mb_strlen($single) > self::MAX_TERM_NAME) {
              $logger->warning('ird_claims: over-long categorical value (@n chars) key=@k id=@id: @v…', [
                '@n' => mb_strlen($single), '@k' => $key, '@id' => $source_id,
                '@v' => mb_substr($single, 0, 60),
              ]);
              continue;
            }

            $paragraph = $is_categorical
              ? $this->buildRefClaim($key, $single, $property_term, $values_parent, $collection_nid)
              : $this->buildLiteralClaim($single, $property_term, $collection_nid);

            $new_refs[] = [
              'target_id'          => $paragraph->id(),
              'target_revision_id' => $paragraph->getRevisionId(),
            ];
          }
          catch (\Throwable $e) {
            $logger->error('ird_claims: value failed key=@k id=@id: @m',
              ['@k' => $key, '@id' => $source_id, '@m' => $e->getMessage()]);
          }
        }
      }
      catch (\Throwable $e) {
        $logger->error('ird_claims: claim failed key=@k id=@id: @m',
          ['@k' => (string) $key, '@id' => $source_id, '@m' => $e->getMessage()]);
      }
    }

    return array_merge($kept_refs, $new_refs);
  }

  /**
   * Whether a claim uses a system property rather than an imported one.
   *
   * Imported property terms always sit under their collection's term
   * (claim_properties: {collection} → {key}); system properties sit at the
   * root. A claim whose property term cannot be loaded is not a system claim:
   * it is this collection's to replace.
   */
  public static function isSystemClaim(ParagraphInterface $claim): bool {
    if (!$claim->hasField('field_claim_property')) {
      return FALSE;
    }
    $property = $claim->get('field_claim_property')->entity;
    if (!$property instanceof TermInterface) {
      return FALSE;
    }
    foreach ($property->get('parent') as $item) {
      if ((int) $item->target_id !== 0) {
        return FALSE;
      }
    }
    return TRUE;
  }

  // ---------------------------------------------------------------------------
  // Node lookup (via this migration's id map)
  // ---------------------------------------------------------------------------

  /**
   * Return the node already migrated for this source id, or NULL on first run.
   */
  private function lookupMigratedNode(string $source_id): ?NodeInterface {
    if ($source_id === '' || !$this->migration) {
      return NULL;
    }
    try {
      $dest = $this->migration->getIdMap()->lookupDestinationIds([$source_id]);
    }
    catch (\Throwable) {
      return NULL;
    }
    $nid = $dest[0][0] ?? NULL;
    if ($nid === NULL) {
      // Fallback: match by provenance field (cross-run safety).
      $matches = $this->nodeStorage()->loadByProperties([
        'type'                       => self::SOLUTION_BUNDLE,
        'field_original_internal_id' => $source_id,
      ]);
      return $matches ? reset($matches) : NULL;
    }
    $node = $this->nodeStorage()->load($nid);
    return $node instanceof NodeInterface ? $node : NULL;
  }

  // ---------------------------------------------------------------------------
  // Claim construction
  // ---------------------------------------------------------------------------

  private function buildLiteralClaim(string $value, TermInterface $property, int $collection_nid): ParagraphInterface {
    return $this->createClaim([
      'field_claim_property'      => ['target_id' => $property->id()],
      'field_claim_literal_value' => $value,
    ], $collection_nid);
  }

  private function buildRefClaim(
    string $key,
    string $value,
    TermInterface $property,
    TermInterface $values_parent,
    int $collection_nid,
  ): ParagraphInterface {
    $key_term   = $this->ensureKeyTerm(self::VOCAB_VALUES, $key, $values_parent);
    $value_term = $this->ensureTerm(self::VOCAB_VALUES, $value, (int) $key_term->id());
    return $this->createClaim([
      'field_claim_property'  => ['target_id' => $property->id()],
      'field_claim_ref_value' => ['target_id' => $value_term->id()],
    ], $collection_nid);
  }

  private function createClaim(array $fields, int $collection_nid): ParagraphInterface {
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = $this->paragraphStorage()->create([
      'type'             => self::PARAGRAPH_BUNDLE,
      'field_claimed_by' => ['target_id' => $collection_nid],
      'field_claimed_at' => $this->time->getRequestTime(),
    ] + $fields);
    $paragraph->save();
    return $paragraph;
  }

  // ---------------------------------------------------------------------------
  // Taxonomy helpers (idempotent, cached)
  // ---------------------------------------------------------------------------

  private function ensureKeyTerm(string $vid, string|int $key, TermInterface $parent): TermInterface {
    return $this->ensureTerm($vid, (string) $key, (int) $parent->id());
  }

  private function ensureTerm(string $vid, string $name, ?int $parent_tid = NULL): TermInterface {
    if ($existing = $this->findTerm($vid, $name, $parent_tid)) {
      return $existing;
    }
    $term = $this->termStorage()->create([
      'vid'    => $vid,
      'name'   => $name,
      'parent' => $parent_tid !== NULL ? [$parent_tid] : [],
    ]);
    $term->save();
    // Refresh cache so subsequent lookups in the same run hit.
    $this->termCache["$vid|$name|" . ($parent_tid ?? 'root')] = $term;
    return $term;
  }

  private function findTerm(string $vid, string $name, ?int $parent_tid = NULL): ?TermInterface {
    $ck = "$vid|$name|" . ($parent_tid ?? 'root');
    if (array_key_exists($ck, $this->termCache)) {
      return $this->termCache[$ck];
    }
    $properties = ['vid' => $vid, 'name' => $name];
    if ($parent_tid !== NULL) {
      $properties['parent'] = $parent_tid;
    }
    $matches = $this->termStorage()->loadByProperties($properties);
    return $this->termCache[$ck] = ($matches ? reset($matches) : NULL);
  }

  // ---------------------------------------------------------------------------
  // Source value helpers
  // ---------------------------------------------------------------------------

  /**
   * Normalize a claim value into a flat list of non-empty scalar strings.
   *
   * @return string[]
   */
  private function normalizeValues(mixed $value, ?string $separator = NULL): array {
    if (is_array($value)) {
      $items = $value;
    }
    elseif ($separator !== NULL && $separator !== '') {
      $items = array_map('trim', explode($separator, (string) $value));
    }
    else {
      $items = [(string) $value];
    }
    $flat = [];
    foreach ($items as $item) {
      $flat[] = is_array($item) ? (string) ($item['value'] ?? reset($item)) : (string) $item;
    }
    return array_values(array_filter($flat, static fn(string $v): bool => trim($v) !== ''));
  }

  private function loadCollectionNid(string $collection_id): int {
    if (isset(self::$collectionNidCache[$collection_id])) {
      return self::$collectionNidCache[$collection_id];
    }
    $matches = $this->nodeStorage()->loadByProperties([
      'type'     => self::COLLECTION_BUNDLE,
      'short_id' => $collection_id,
    ]);
    if (!$matches) {
      throw new \InvalidArgumentException("No collection found with short_id={$collection_id}.");
    }
    return self::$collectionNidCache[$collection_id] = (int) reset($matches)->id();
  }

  // ---------------------------------------------------------------------------
  // Storage accessors
  // ---------------------------------------------------------------------------

  private function nodeStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('node');
  }

  private function termStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('taxonomy_term');
  }

  private function paragraphStorage(): EntityStorageInterface {
    return $this->entityTypeManager->getStorage('paragraph');
  }

}
