<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\Merger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Side-by-side resolution of one candidate pair.
 *
 * The comparison shows CORE fields only. Claims are summarised rather than
 * diffed, because they are additive by definition and because diffing them
 * would need vocabulary mappings, which nothing is allowed to wait on
 * (ADR #14).
 */
final class ResolveForm extends FormBase {

  /**
   * Prefixes used to compact predicate URIs for display.
   */
  private const PREFIXES = [
    'http://purl.org/dc/terms/' => 'dct:',
    'http://www.w3.org/ns/dcat#' => 'dcat:',
    'http://xmlns.com/foaf/0.1/' => 'foaf:',
    'https://sti-portal.fao.org/ontology#' => 'sti:',
  ];

  private ?NodeInterface $collection = NULL;
  private ?NodeInterface $solution = NULL;
  private ?NodeInterface $candidate = NULL;

  /**
   * Constructs the form.
   *
   * Note: FormBase already declares $configFactory through
   * ConfigFormBaseTrait, so it must not be promoted here — a readonly
   * promoted property of the same name is a fatal error. Settings are read
   * through FormBase::config() instead.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly Merger $merger,
    private readonly CandidateStore $candidates,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('sti_dedupe.merger'),
      $container->get('sti_dedupe.candidates'),
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

    if (!$solution || !$candidate) {
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

    $form['actions'] = ['#type' => 'actions'];

    $form['actions']['keep_existing'] = [
      '#type' => 'submit',
      '#name' => 'keep_existing',
      '#value' => $this->t('Same — keep existing'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
      '#description' => $this->t('Moves the claims and raw record onto the existing node, shares it into this collection, repoints the migrate map, deletes this node and leaves a redirect.'),
    ];

    $form['actions']['keep_both'] = [
      '#type' => 'submit',
      '#name' => 'keep_both',
      '#value' => $this->t('Same — keep both'),
      '#attributes' => ['class' => ['btn', 'btn-secondary']],
    ];

    $form['actions']['different'] = [
      '#type' => 'submit',
      '#name' => 'different',
      '#value' => $this->t('Not the same'),
      '#attributes' => ['class' => ['btn', 'btn-secondary']],
    ];

    $form['legend'] = [
      '#theme' => 'item_list',
      '#items' => [
        $this->t('<strong>Same — keep existing</strong>: @loser is deleted; its claims and raw payload move to @winner.', [
          '@loser' => $solution->label(),
          '@winner' => $candidate->label(),
        ]),
        $this->t('<strong>Same — keep both</strong>: both records stay, linked by a sti:sameInnovationAs claim attributed to you.'),
        $this->t('<strong>Not the same</strong>: both records stay and the decision is recorded, so this pair is not offered again.'),
      ],
    ];

    return $form;
  }

  /**
   * Builds the core-field comparison table.
   */
  private function buildDiff(NodeInterface $new, NodeInterface $existing): array {
    $fields = (array) ($this->config('sti_dedupe.settings')->get('compare_fields') ?? []);
    $predicates = $this->predicateMap();

    $rows = [];
    foreach ($fields as $fieldName) {
      if (!$new->hasField($fieldName) && !$existing->hasField($fieldName)) {
        continue;
      }
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
        $this->t('Existing — @collection (survives)', ['@collection' => $this->collectionLabel($existing)]),
        $this->t('Diff'),
      ],
      '#rows' => $rows,
      '#attributes' => ['class' => ['table', 'table-striped']],
    ];
  }

  /**
   * Summarises the claims on each side. Claims are additive, so not diffed.
   */
  private function buildClaimSummary(NodeInterface $new, NodeInterface $existing): array {
    return [
      '#theme' => 'item_list',
      '#title' => $this->t('Claims'),
      '#items' => [
        $this->t('New: @count claims, which would move to the existing record.', [
          '@count' => $new->hasField('field_claims') ? $new->get('field_claims')->count() : 0,
        ]),
        $this->t('Existing: @count claims already attached.', [
          '@count' => $existing->hasField('field_claims') ? $existing->get('field_claims')->count() : 0,
        ]),
      ],
    ];
  }

  /**
   * Maps field names to compacted RDF predicates from the bundle's config.
   *
   * @return array<string, string>
   *   Field name to compacted predicate, e.g. "title" => "dct:title".
   */
  private function predicateMap(): array {
    $type = $this->entityTypeManager->getStorage('node_type')->load('solution');
    if (!$type) {
      return [];
    }
    $fields = (array) ($type->getThirdPartySetting('rdf_sync', 'fields') ?? []);

    $map = [];
    foreach ($fields as $fieldName => $properties) {
      $predicate = NULL;
      foreach ((array) $properties as $property) {
        if (isset($property['predicate'])) {
          $predicate = (string) $property['predicate'];
          break;
        }
      }
      if ($predicate === NULL) {
        continue;
      }
      foreach (self::PREFIXES as $namespace => $prefix) {
        if (str_starts_with($predicate, $namespace)) {
          $predicate = $prefix . substr($predicate, strlen($namespace));
          break;
        }
      }
      $map[$fieldName] = $predicate;
    }
    return $map;
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
    $solution = $this->solution;
    $candidate = $this->candidate;

    if (!$solution || !$candidate) {
      return;
    }

    switch ($trigger) {
      case 'keep_existing':
        $loserLabel = $solution->label();
        $this->merger->keepExisting($solution, $candidate);
        $this->messenger()->addStatus($this->t('Merged “@loser” into “@winner”. Its claims and raw record moved, and the migrate map now points at the surviving node.', [
          '@loser' => $loserLabel,
          '@winner' => $candidate->label(),
        ]));
        break;

      case 'keep_both':
        $this->merger->keepBoth($solution, $candidate);
        $this->messenger()->addStatus($this->t('Recorded as the same innovation, kept separate and linked with sti:sameInnovationAs.'));
        break;

      case 'different':
        $this->merger->markDifferent($solution, $candidate);
        $this->messenger()->addStatus($this->t('Recorded as different. This pair will not be offered again.'));
        break;
    }

    if ($this->collection) {
      $form_state->setRedirectUrl(Url::fromRoute('sti_dedupe.review', ['collection' => $this->collection->id()]));
    }
  }

}
