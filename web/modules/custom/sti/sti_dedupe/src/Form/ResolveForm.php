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
use Drupal\sti_dedupe\InnovationLookup;
use Drupal\sti_dedupe\Linker;
use Drupal\sti_dedupe\Prefixes;
use Drupal\sti_rdf\Service\SyncReaderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Side-by-side review of one candidate pair, for one collection.
 *
 * The solution is the collection's own record; the candidate may be in any
 * collection. "Same innovation" writes a sameInnovationAs claim on the
 * solution only; "not the same" stops the pair being offered to this
 * collection again. Neither touches the candidate or an ATIO innovation.
 *
 * The comparison shows core fields only. Claims are summarised, not diffed:
 * collections disagreeing is normal.
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
    private readonly InnovationLookup $innovations,
    private readonly SyncReaderInterface $rdfSyncReader,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('sti_dedupe.linker'),
      $container->get('sti_dedupe.candidates'),
      $container->get('sti_dedupe.innovation_lookup'),
      $container->get('sti_rdf.sync_reader'),
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
    ?NodeInterface $node = NULL,
    ?NodeInterface $solution = NULL,
    ?NodeInterface $candidate = NULL,
  ): array {
    $collection = $node;
    $this->collection = $collection;
    $this->solution = $solution;
    $this->candidate = $candidate;

    if (!$collection || !$solution || !$candidate || $solution->bundle() !== ContentModel::SOLUTION || $candidate->bundle() !== ContentModel::SOLUTION) {
      $form['error'] = ['#markup' => $this->t('This pair could not be loaded.')];
      return $form;
    }
    if ((int) $solution->get(ContentModel::AUDIENCE)->target_id !== (int) $collection->id()) {
      $form['error'] = ['#markup' => $this->t('%title is not a record of %collection.', [
        '%title' => $solution->label(),
        '%collection' => $collection->label(),
      ])];
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
        $this->t('<strong>Same innovation</strong>: %collection records that its record is the same innovation as the other one. Nothing is merged or deleted, the other record is not changed, and ATIO innovations stay as they are; ATIO curators use the link when they next update the innovation.', ['%collection' => $collection->label()]),
        $this->t('<strong>Not the same</strong>: the decision is recorded so this pair is not offered to %collection again.', ['%collection' => $collection->label()]),
      ],
    ];

    return $form;
  }

  /**
   * Shows which ATIO innovations the two records are on, read-only.
   */
  private function buildInnovationSection(NodeInterface $solution, NodeInterface $candidate): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $describe = function (NodeInterface $record) use ($storage): string {
      $labels = array_map(static fn($innovation): string => (string) $innovation->label(), $storage->loadMultiple($this->innovations->derivedFrom([(int) $record->id()])));
      return $labels ? implode(', ', $labels) : (string) $this->t('none');
    };
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('ATIO innovations (not changed by this decision)'),
      '#items' => [
        $this->t('This record: @list', ['@list' => $describe($solution)]),
        $this->t('Other record: @list', ['@list' => $describe($candidate)]),
      ],
    ];
  }

  /**
   * Builds the core-field comparison table.
   */
  private function buildDiff(NodeInterface $new, NodeInterface $existing): array {
    $fields = (array) ($this->config('sti_dedupe.settings')->get('compare_fields') ?? []);
    $predicates = [];
    foreach ($this->rdfSyncReader->fieldSyncs('node', ContentModel::SOLUTION) as $rdf_sync_mapping) {
      $predicates[$rdf_sync_mapping['field']] ??= Prefixes::compact($rdf_sync_mapping['predicate']);
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
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = (string) ($form_state->getTriggeringElement()['#name'] ?? '');
    if (!$this->collection || !$this->solution || !$this->candidate) {
      return;
    }

    try {
      if ($trigger === 'same') {
        $this->linker->decideSame($this->collection, $this->solution, $this->candidate);
        $this->messenger()->addStatus($this->t('Recorded as the same innovation.'));
      }
      elseif ($trigger === 'different') {
        $this->linker->decideDifferent($this->collection, $this->solution, $this->candidate);
        $this->messenger()->addStatus($this->t('Recorded as different. This pair will not be offered again.'));
      }
    }
    catch (\LogicException $e) {
      // Includes the InvalidArgumentException for a record outside the
      // collection, which buildForm() already refuses to offer.
      $this->messenger()->addError($e->getMessage());
      return;
    }

    $form_state->setRedirectUrl(Url::fromRoute('sti_dedupe.review', ['node' => $this->collection->id()]));
  }

}
