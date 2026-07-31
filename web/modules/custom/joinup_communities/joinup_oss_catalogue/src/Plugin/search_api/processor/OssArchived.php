<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\search_api\processor;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Excludes archived OSS solutions from being indexed.
 */
#[SearchApiProcessor(
  id: "oss_archived",
  label: new TranslatableMarkup("Archived OSS solutions"),
  description: new TranslatableMarkup("Exclude archived OSS solutions from being indexed."),
  stages: [
    "alter_items" => 0,
  ],
)]
class OssArchived extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'node' && isset($datasource->getBundles()['oss_solution'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      if ($object instanceof OssSolutionInterface && $object->isArchived()) {
        unset($items[$item_id]);
      }
    }
  }

}