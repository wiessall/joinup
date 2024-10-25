<?php

declare(strict_types=1);

namespace Drupal\joinup_debug\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Task\IndexTaskManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Portal debug form.
 */
final class VocabularyIndexForm extends FormBase {

  /**
   * Constructs a new VocabularyIndexForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\search_api\Task\IndexTaskManagerInterface $indexTaskManager
   *   The index task manager.
   */
  public function __construct(protected EntityTypeBundleInfoInterface $bundleInfo, protected EntityTypeManagerInterface $entityTypeManager, protected IndexTaskManagerInterface $indexTaskManager) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('search_api.index_task_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_debug_topic_reindex';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $vocabularies = $this->bundleInfo->getBundleInfo('taxonomy_term');
    foreach ($vocabularies as $vocabulary => $info) {
      $vocabularies[$vocabulary] = ucfirst(str_replace(['_', '-'], ' ', $vocabulary));
    }

    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#description' => $this->t('Select a vocabulary to view the index status and reindex.'),
      '#options' => $vocabularies,
      '#ajax' => [
        'callback' => '::loadIndexStatus',
        'wrapper' => 'joinup-debug-topic-reindex-wrapper',
      ],
      '#required' => TRUE,
    ];

    $form['index_status'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'joinup-debug-topic-reindex-wrapper',
      ],
    ];

    if ($form_state->hasValue('vocabulary')) {
      $form['index_status']['details']['#markup'] = $this->t('Index status for vocabulary %vocabulary: %indexed/%total', [
        '%vocabulary' => $form_state->getValue('vocabulary'),
        '%indexed' => $form_state->get('indexed_items'),
        '%total' => $form_state->get('total_items'),
      ]);

      // Optionally, filter by specific terms - textarea with term IDs.
      $form['index_status']['filter'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Filter by term IDs'),
        '#description' => $this->t('Enter a list of term IDs to filter the index by. One term ID per line.'),
      ];
    }

    $form['actions'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reindex'),
      '#submit' => ['::reindex'],
    ];

    return $form;
  }

  /**
   * Ajax callback to load the index status.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The response form part.
   */
  public function loadIndexStatus(array $form, FormStateInterface $form_state): array {
    $vocabulary = $form_state->getValue('vocabulary');
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('published');
    $datasourceId = 'entity:taxonomy_term';
    $indexedItems = $index->getTrackerInstance()->getIndexedItemsCount($datasourceId);
    $remainingItems = $index->getTrackerInstance()->getRemainingItemsCount($datasourceId);
    $totalItems = $index->getTrackerInstance()->getTotalItemsCount($datasourceId);
    $form['index_status']['details']['#markup'] = $this->t('Index status for vocabulary %vocabulary: %indexed indexed, %remaining remaining, %total total', [
      '%vocabulary' => $vocabulary,
      '%indexed' => $indexedItems,
      '%remaining' => $remainingItems,
      '%total' => $totalItems,
    ]);

    return $form['index_status'];
  }

  /**
   * Reindex the selected vocabulary.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function reindex(array $form, FormStateInterface $form_state): void {
    $vocabulary = $form_state->getValue('vocabulary');
    $filter = array_filter(array_map('trim', explode("\n", $form_state->getValue('filter'))));

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('published');
    $remainingItemIds = $index->getTrackerInstance()->getRemainingItems(-1, 'entity:taxonomy_term');
    $items = $index->loadItemsMultiple($remainingItemIds);
    $items = array_keys($items);

    $items = array_merge($items, $this->getSearchApiItemIds('node', 'field_topic', $filter));
    $items = array_merge($items, $this->getSearchApiItemIds('rdf_entity', 'field_topic', $filter));

    $batch = [
      'title' => new TranslatableMarkup('Reindexing vocabulary %vocabulary', ['%vocabulary' => $vocabulary]),
      'finished' => [self::class, 'batchFinished'],
    ];

    foreach ($items as $item) {
      // Add items in a batch operation to not stress the server.
      $batch['operations'][] = [
        [self::class, 'indexItems'],
        [$item],
      ];
    }

    batch_set($batch);
  }

  /**
   * Index items in a batch operation.
   *
   * @param string $item
   *   The datasource ID.
   * @param array $context
   *   The batch context.
   */
  public static function indexItems(string $item, array &$context): void {
    /** @var \Drupal\search_api\IndexInterface $index */
    $index = \Drupal::entityTypeManager()->getStorage('search_api_index')->load('published');
    $loadedItems = $index->loadItemsMultiple([$item]);
    $index->indexSpecificItems($loadedItems);
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch was successful.
   * @param array $results
   *   The batch results.
   * @param array $operations
   *   The batch operations.
   */
  public static function batchFinished(bool $success, array $results, array $operations): void {
    if ($success) {
      \Drupal::messenger()->addStatus('The batch has been processed successfully.');
    }
    else {
      \Drupal::messenger()->addError('An error occurred. Please check the logs for more information.');
    }
  }

  /**
   * Returns a list of search API item IDs for a given entity type and field.
   *
   * @param string $entityTypeId
   *   The entity type ID.
   * @param string $fieldName
   *   The field name that refers to the term.
   * @param array|null $termIds
   *   (optional) The term IDs to filter by.
   *
   * @return array
   *   The list of search API item IDs.
   */
  protected function getSearchApiItemIds(string $entityTypeId, string $fieldName, ?array $termIds): array {
    $query = $this->entityTypeManager->getStorage($entityTypeId)->getQuery();
    if ($termIds) {
      $query->condition($fieldName, $termIds, 'IN');
    }
    else {
      $query->exists($fieldName);
    }
    $query->accessCheck(FALSE);

    return array_map(function (mixed $id) use ($entityTypeId): string {
      return "entity:$entityTypeId/$id:en";
    }, $query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

}
