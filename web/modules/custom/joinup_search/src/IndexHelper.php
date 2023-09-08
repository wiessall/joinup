<?php declare(strict_types = 1);

namespace Drupal\joinup_search;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Helper class for doing operations related to the index.
 *
 * This is mostly to be used during updates as we have had numerous cases where
 * items in the index need to be force updated. Since we use cron to index
 * items, simply marking them as changed is not enough.
 */
final class IndexHelper implements IndexHelperInterface {

  /**
   * Constructs an IndexHelper object.
   */
  public function __construct(protected readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function reIndexItems(string $entityType, array $entityIds, string $language = 'en', array $indexIds = ['published']): void {
    foreach ($indexIds as $indexId) {
      /** @var \Drupal\search_api\Entity\Index $index */
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($indexId);
      $itemIds = $this->generateItemIds($entityType, $entityIds, $language);
      $items = $index->loadItemsMultiple($itemIds);
      $index->getServerInstance()->indexItems($index, $items);
    }
  }

  /**
   * Generate the search API IDs for the given entity type and IDs.
   *
   * @param string $entityType
   *   The entity type.
   * @param array $entityIds
   *   The entity IDs.
   * @param $language
   *   (optional) The language code. Defaults to 'en'.
   *
   * @return array
   *   The search API item IDs.
   */
  protected function generateItemIds(string $entityType, array $entityIds, string $language = 'en'): array {
    $itemIds = [];
    foreach ($entityIds as $entityId) {
      $itemIds[] = 'entity:' . $entityType . ':' . $entityId . ':' . $language;
    }
    return $itemIds;
  }

}
