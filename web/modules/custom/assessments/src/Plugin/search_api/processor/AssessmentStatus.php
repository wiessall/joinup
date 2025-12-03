<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\search_api\processor;

use Drupal\assessments\AssessmentInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Excludes unpublished assessment entities from the index.
 *
 * @SearchApiProcessor(
 *   id = "assessment_status",
 *   label = @Translation("Assessment status"),
 *   description = @Translation("Exclude unpublished assessments."),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class AssessmentStatus extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'assessment') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      if ($object instanceof AssessmentInterface && !$object->isPublished()) {
        unset($items[$item_id]);
      }
    }
  }

}
