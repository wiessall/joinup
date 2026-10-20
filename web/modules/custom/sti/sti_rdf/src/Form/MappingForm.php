<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Form;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\sti_rdf\Service\MappingCuration;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maps this collection's properties to those of another collection.
 *
 * One table. Per property of this collection: its mappings into the chosen
 * target (ATIO, or another collection), locked and with their state, then an
 * empty row to add one.
 * Selected new rows are proposed; selected proposals are accepted or
 * rejected.
 */
final class MappingForm extends FormBase {

  /**
   * The target choice that stands for ATIO's properties.
   */
  private const ATIO = 'atio';

  // Protected and not readonly: a rebuilt form is serialized, and
  // DependencySerializationTrait, running in FormBase's scope, can neither
  // see private properties of this class nor restore readonly ones.
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected MappingCuration $curation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('sti_rdf.mapping_curation'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sti_rdf_mapping';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    $form_state->set('collection', $node);
    $form['export'] = [
      '#type' => 'link',
      '#title' => $this->t('Download accepted mappings (Turtle)'),
      '#url' => Url::fromRoute('sti_rdf.mappings.export', ['node' => $node->id()]),
    ];
    $form['target'] = [
      '#type' => 'select',
      '#title' => $this->t('Map to the properties of'),
      '#options' => [self::ATIO => $this->t('ATIO')] + $this->collectionOptions($node),
      '#empty_option' => $this->t('- Choose ATIO or a collection -'),
      '#ajax' => ['callback' => '::refresh', 'wrapper' => 'sti-rdf-mappings'],
      '#limit_validation_errors' => [['target']],
    ];
    $form['mappings'] = ['#type' => 'container', '#attributes' => ['id' => 'sti-rdf-mappings']];

    $theirs = $this->targets((string) $form_state->getValue('target'));
    if ($theirs === NULL) {
      return $form;
    }
    $relations = $this->entityFieldManager->getFieldStorageDefinitions('paragraph')['field_mapping_relation']->getSetting('allowed_values');

    // This collection's mappings into the chosen targets, by subject.
    $existing = [];
    foreach ($this->curation->mappings($node) as $mapping) {
      if (isset($theirs[$this->target($mapping)])) {
        $existing[(int) $mapping->get('field_mapping_subject')->target_id][] = $mapping;
      }
    }

    $form['mappings']['rows'] = [
      '#type' => 'table',
      '#header' => ['', $this->t('Property'), $this->t('Relation'), $this->t('Target property'), $this->t('State')],
      '#empty' => $this->t('No claim properties on one side yet, or no ATIO property is mapped in rdf_sync.'),
    ];
    // Per property: its mappings into the target ('m' + mapping id), then
    // one empty row to add another ('n' + term id).
    foreach ($theirs ? $this->curation->properties($node) : [] as $tid => $term) {
      $label = $term->label();
      foreach ($existing[$tid] ?? [] as $mapping) {
        $state = $this->curation->state($mapping);
        $row = $this->row($label, $relations, $theirs, $state === MappingCuration::PROPOSED, $state, $mapping->get('field_mapping_relation')->value, $this->target($mapping));
        // Proposed on this page a moment ago: ticked, ready to accept.
        $row['select']['#default_value'] = in_array((int) $mapping->id(), (array) $form_state->get('fresh'), TRUE);
        $form['mappings']['rows']['m' . $mapping->id()] = $row;
      }
      $form['mappings']['rows']['n' . $tid] = $this->row($label, $relations, $theirs, TRUE, isset($existing[$tid]) ? '' : $this->t('unmapped'));
    }
    $form['mappings']['actions'] = ['#type' => 'actions'];
    foreach (['propose' => $this->t('Propose selected'), 'accept' => $this->t('Accept selected'), 'reject' => $this->t('Reject selected')] as $op => $label) {
      $form['mappings']['actions'][$op] = ['#type' => 'submit', '#value' => $label, '#op' => $op];
    }
    return $form;
  }

  /**
   * Returns the table for the chosen collection.
   */
  public function refresh(array &$form): array {
    return $form['mappings'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (($form_state->getTriggeringElement()['#op'] ?? NULL) !== 'propose') {
      return;
    }
    foreach ($this->selected($form_state, 'n') as $tid => $row) {
      if (empty($row['relation']) || empty($row['object'])) {
        $form_state->setErrorByName("rows][n$tid", $this->t('Choose a relation and a target property for every selected new row.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $op = $form_state->getTriggeringElement()['#op'] ?? NULL;
    $collection = $form_state->get('collection');
    // Keep the chosen collection's table on screen after the action, with
    // fresh rows instead of the ones just submitted.
    $form_state->setRebuild();
    $input = $form_state->getUserInput();
    unset($input['rows']);
    $form_state->setUserInput($input);

    if ($op === 'propose') {
      $terms = $this->entityTypeManager->getStorage('taxonomy_term');
      $rows = [];
      foreach ($this->selected($form_state, 'n') as $tid => $row) {
        $rows[] = ['subject' => $terms->load($tid), 'relation' => $row['relation']]
          + ($form_state->getValue('target') === self::ATIO
            ? ['predicate' => $row['object']]
            : ['object' => $terms->load($row['object'])]);
      }
      $before = array_map(static fn($mapping): int => (int) $mapping->id(), $this->curation->mappings($collection));
      try {
        $this->messenger()->addStatus($this->t('@n mapping(s) proposed.', ['@n' => $this->curation->propose($collection, $rows)]));
        $after = array_map(static fn($mapping): int => (int) $mapping->id(), $this->curation->mappings($collection));
        $form_state->set('fresh', array_values(array_diff($after, $before)));
      }
      catch (\InvalidArgumentException $e) {
        $this->messenger()->addError($e->getMessage());
      }
      return;
    }

    $selected = $this->selected($form_state, 'm');
    $count = 0;
    // Only proposals this collection holds, whatever ids the browser sent.
    foreach ($this->curation->mappings($collection) as $mapping) {
      if (isset($selected[$mapping->id()]) && $this->curation->state($mapping) === MappingCuration::PROPOSED) {
        $op === 'accept' ? $this->curation->accept($mapping) : $this->curation->reject($mapping);
        $count++;
      }
    }
    $this->messenger()->addStatus($op === 'accept'
      ? $this->t('@n mapping(s) accepted.', ['@n' => $count])
      : $this->t('@n mapping(s) rejected.', ['@n' => $count]));
  }

  /**
   * Builds one table row; a row with a relation set is an existing mapping.
   */
  private function row(string $label, array $relations, array $theirs, bool $selectable, string|\Stringable $state, ?string $relation = NULL, string|int|null $object = NULL): array {
    $locked = $relation !== NULL;
    return [
      'select' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Select'),
        '#title_display' => 'invisible',
        '#access' => $selectable,
      ],
      'property' => ['#plain_text' => $label],
      // An existing mapping is shown as text: it cannot be edited, and
      // disabled selects render badly in the theme.
      'relation' => $locked
        ? ['#plain_text' => (string) ($relations[$relation] ?? $relation)]
        : ['#type' => 'select', '#options' => $relations, '#empty_option' => '-'],
      'object' => $locked
        ? ['#plain_text' => (string) ($theirs[$object] ?? '')]
        : ['#type' => 'select', '#options' => $theirs, '#empty_option' => '-'],
      'state' => ['#plain_text' => (string) $state],
    ];
  }

  /**
   * Returns the selected rows of one kind, keyed by id without the prefix.
   *
   * @param string $prefix
   *   'm' for existing mappings (keyed by paragraph id), 'n' for new rows
   *   (keyed by property term id).
   */
  private function selected(FormStateInterface $form_state, string $prefix): array {
    $out = [];
    foreach ((array) $form_state->getValue('rows') as $key => $row) {
      if (!empty($row['select']) && str_starts_with((string) $key, $prefix)) {
        $out[(int) substr((string) $key, 1)] = $row;
      }
    }
    return $out;
  }

  /**
   * Returns what the chosen target offers, keyed as mappings store it.
   *
   * @return array<string|int, string>|null
   *   ATIO properties keyed by predicate URI, or a collection's properties
   *   keyed by term id; NULL when nothing usable is chosen.
   */
  private function targets(string $choice): ?array {
    if ($choice === self::ATIO) {
      return $this->curation->atioPredicates();
    }
    $collection = ctype_digit($choice) ? $this->entityTypeManager->getStorage('node')->load($choice) : NULL;
    return $collection instanceof NodeInterface
      ? array_map(static fn($term): string => $term->label(), $this->curation->properties($collection))
      : NULL;
  }

  /**
   * Returns a mapping's target: a term id or an ATIO predicate URI.
   */
  private function target(ParagraphInterface $mapping): string|int|null {
    return $mapping->get('field_mapping_object')->target_id ?? $mapping->get('field_mapping_target_predicate')->value;
  }

  /**
   * Returns the other collections, by title.
   *
   * @return array<int, string>
   *   Titles keyed by node id.
   */
  private function collectionOptions(NodeInterface $collection): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', $collection->bundle())
      ->condition('nid', $collection->id(), '<>')
      ->sort('title')
      ->execute();
    return array_map(static fn(NodeInterface $node): string => $node->label(), $storage->loadMultiple($ids));
  }

}
