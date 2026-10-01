<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\Exception\SurvivorRequiredException;
use Drupal\sti_dedupe\Linker;
use Drupal\sti_dedupe\Prefixes;
use Drupal\sti_rdf\Service\MappingReaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Side-by-side review of one candidate pair of solutions.
 *
 * Two outcomes, neither of which edits or deletes a solution: "same
 * innovation" links both (and anything already linked to either) to one
 * ATIO innovation; "not the same" stops the pair being offered again.
 *
 * The comparison shows core fields only. Claims are summarised, not diffed:
 * collections disagreeing is normal, and the disagreements that matter
 * (single-valued properties) are surfaced as claim conflicts on the
 * innovation once the pair is linked.
 */
final class ResolveForm extends FormBase {

  /**
   * The collection whose queue the reviewer came from.
   */
  private ?NodeInterface $collection = NULL;

  /**
   * The newly imported solution.
   */
  private ?NodeInterface $solution = NULL;

  /**
   * The existing solution it may duplicate.
   */
  private ?NodeInterface $candidate = NULL;

  /**
   * Constructs the form.
   *
   * FormBase already declares $configFactory, so it is not promoted here;
   * settings are read through FormBase::config().
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Linker $linker,
    private readonly CandidateStore $candidates,
    private readonly MappingReaderInterface $mappingReader,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('sti_dedupe.linker'),
      $container->get('sti_dedupe.candidates'),
      $container->get('sti_rdf.mapping_reader'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sti_dedupe_resolve_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    ?NodeInterface $collection = NULL,
    ?NodeInterface $solution = NULL,
    ?NodeInterface $candidate = NULL,
  ): array {
    $this->collection = $collection;
    $this->solution = $solution;
    $this->candidate = $candidate;

    if (!$solution || !$candidate || $solution->bundle() !== ContentModel::SOLUTION || $candidate->bundle() !== ContentModel::SOLUTION) {
      $form['error'] = ['#markup' => $this->t('This pair could not be loaded.')];
      return $form;
    }

    $stored = $this->candidates->forSource((int) $solution->id());
    $entry = $stored[(int) $candidate->id()] ?? ['score' => 0.0, 'reasons' => ''];
    $position = array_search((int) $candidate->id(), array_keys($stored), TRUE);

    $form['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Candidate @n of @total · match <strong>@score</strong> — @reasons', [
        '@n' => $position === FALSE ? 1 : $position + 1,
        '@total' => max(1, count($stored)),
        '@score' => number_format((float) $entry['score'], 2),
        '@reasons' => $entry['reasons'],
      ]),
    ];

    $form['diff'] = $this->buildDiff($solution, $candidate);
    $form['claims'] = $this->buildClaimSummary($solution, $candidate);
    $form['innovation'] = $this->buildInnovationSection($solution, $candidate);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['same'] = [
      '#type' => 'submit',
      '#name' => 'same',
      '#value' => $this->t('Same innovation'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];
    $form['actions']['different'] = [
      '#type' => 'submit',
      '#name' => 'different',
      '#value' => $this->t('Not the same'),
      '#attributes' => ['class' => ['btn', 'btn-secondary']],
      '#limit_validation_errors' => [],
      '#submit' => ['::submitForm'],
    ];

    $form['legend'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('<strong>Same innovation</strong>: both records stay as they are. They, and every record already linked to either, are listed as sources of one ATIO innovation. If none exists yet, only the decision is recorded.'),
        $this->t('<strong>Not the same</strong>: the decision is recorded so this pair is not offered again.'),
      ],
    ];

    return $form;
  }

  /**
   * Describes what "same" would do, and asks for a survivor if needed.
   */
  private function buildInnovationSection(NodeInterface $solution, NodeInterface $candidate): array {
    $plan = $this->linker->plan($solution, $candidate);
    $section = [
      '#type' => 'details',
      '#title' => $this->t('ATIO innovation'),
      '#open' => TRUE,
    ];

    $section['cluster'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('"Same" would link @count source records.', ['@count' => count($plan->cluster)]),
    ];

    $innovations = $this->entityTypeManager->getStorage('node')->loadMultiple($plan->innovations);
    if (!$innovations) {
      $section['none'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('None of them is on an ATIO innovation yet; only the decision will be recorded.'),
      ];
      return $section;
    }

    $options = [];
    foreach ($innovations as $nid => $innovation) {
      $options[$nid] = $this->t('@title (@status)', [
        '@title' => $innovation->label(),
        '@status' => $innovation instanceof NodeInterface && $innovation->isPublished() ? $this->t('published') : $this->t('unpublished'),
      ]);
    }

    if (!$plan->needsSurvivor()) {
      $section['target'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('They will be listed as sources of %title.', ['%title' => reset($options)]),
      ];
      return $section;
    }

    $section['survivor'] = [
      '#type' => 'radios',
      '#title' => $this->t('These records are on @count innovations. Which one survives?', ['@count' => count($options)]),
      '#description' => $this->t('The survivor gains every source. The others are unpublished, not deleted, and their URIs redirect to the survivor.'),
      '#options' => $options,
    ];
    return $section;
  }

  /**
   * Builds the core-field comparison table.
   */
  private function buildDiff(NodeInterface $new, NodeInterface $existing): array {
    $fields = (array) ($this->config('sti_dedupe.settings')->get('compare_fields') ?? []);
    $predicates = [];
    foreach ($this->mappingReader->fieldMappings('node', ContentModel::SOLUTION) as $mapping) {
      $predicates[$mapping['field']] ??= Prefixes::compact($mapping['predicate']);
    }

    $rows = [];
    foreach ($fields as $fieldName) {
      $left = $this->fieldToString($new, $fieldName);
      $right = $this->fieldToString($existing, $fieldName);
      if ($left === NULL && $right === NULL) {
        continue;
      }
      if ($left !== NULL && $right !== NULL) {
        $status = $left === $right ? $this->t('same') : $this->t('differs');
      }
      else {
        $status = $left !== NULL ? $this->t('new only') : $this->t('existing only');
      }
      $rows[] = [
        'property' => $predicates[$fieldName] ?? $fieldName,
        'new' => $left ?? '—',
        'existing' => $right ?? '—',
        'status' => $status,
      ];
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Core fields'),
      '#header' => [
        $this->t('Property'),
        $this->t('New — @collection', ['@collection' => $this->collectionLabel($new)]),
        $this->t('Existing — @collection', ['@collection' => $this->collectionLabel($existing)]),
        $this->t('Diff'),
      ],
      '#rows' => $rows,
      '#attributes' => ['class' => ['table', 'table-striped']],
    ];
  }

  /**
   * Summarises the claims on each side.
   */
  private function buildClaimSummary(NodeInterface $new, NodeInterface $existing): array {
    $count = static fn(NodeInterface $node): int => $node->hasField(ContentModel::CLAIMS) ? $node->get(ContentModel::CLAIMS)->count() : 0;
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Claims'),
      '#items' => [
        $this->t('New: @count claims.', ['@count' => $count($new)]),
        $this->t('Existing: @count claims.', ['@count' => $count($existing)]),
        $this->t('Claims are never moved or merged. Disagreements on single-valued ATIO properties appear under "Claim conflicts" once the records share an innovation.'),
      ],
    ];
  }

  /**
   * Renders a field as a comparable string, or NULL when empty.
   */
  private function fieldToString(NodeInterface $node, string $fieldName): ?string {
    if (!$node->hasField($fieldName) || $node->get($fieldName)->isEmpty()) {
      return NULL;
    }
    $parts = [];
    foreach ($node->get($fieldName) as $item) {
      $value = $item->getValue();
      if (isset($value['target_id'])) {
        $referenced = $item->entity ?? NULL;
        $parts[] = $referenced ? (string) $referenced->label() : (string) $value['target_id'];
      }
      elseif (isset($value['uri'])) {
        $parts[] = (string) $value['uri'];
      }
      elseif (isset($value['value'])) {
        $parts[] = trim(strip_tags((string) $value['value']));
      }
    }
    $string = trim(implode(', ', array_filter($parts)));
    return $string === '' ? NULL : mb_substr($string, 0, 400);
  }

  /**
   * Returns a solution's collection label, or a dash.
   */
  private function collectionLabel(NodeInterface $solution): string {
    if (!$solution->hasField('og_audience') || $solution->get('og_audience')->isEmpty()) {
      return '—';
    }
    $collection = $solution->get('og_audience')->entity;
    return $collection instanceof NodeInterface ? (string) $collection->label() : '—';
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if ($trigger === 'same' && isset($form['innovation']['survivor']) && !$form_state->getValue('survivor')) {
      $form_state->setErrorByName('survivor', $this->t('Choose the innovation that survives.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if (!$this->solution || !$this->candidate) {
      return;
    }

    if ($trigger === 'same') {
      $survivor = $form_state->getValue('survivor');
      try {
        $result = $this->linker->linkSame($this->solution, $this->candidate, $survivor ? (int) $survivor : NULL);
      }
      catch (SurvivorRequiredException) {
        // The records joined another innovation since the form was built.
        $this->messenger()->addError($this->t('These records are now on several innovations. Choose the one that survives.'));
        $form_state->setRebuild();
        return;
      }

      if ($result->innovation === NULL) {
        $this->messenger()->addStatus($this->t('Recorded as the same innovation. No ATIO innovation exists for these records yet.'));
      }
      else {
        $this->messenger()->addStatus($this->t('Recorded as the same innovation: @added source record(s) added, @replaced innovation(s) replaced.', [
          '@added' => count($result->added),
          '@replaced' => count($result->replaced),
        ]));
        if (!$result->conflictsRefreshed) {
          $this->messenger()->addWarning($this->t('Claim conflicts could not be refreshed right away and were queued for the next cron run.'));
        }
      }
    }
    elseif ($trigger === 'different') {
      try {
        $this->linker->markDifferent($this->solution, $this->candidate);
        $this->messenger()->addStatus($this->t('Recorded as different. This pair will not be offered again.'));
      }
      catch (\LogicException $e) {
        $this->messenger()->addError($e->getMessage());
        return;
      }
    }

    if ($this->collection) {
      $form_state->setRedirectUrl(Url::fromRoute('sti_dedupe.review', ['collection' => $this->collection->id()]));
    }
  }

}
