<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\search_api\processor;

use Drupal\assessments\AssessmentInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;

/**
 * Handles solutions belonging to the collector group and the group.
 *
 * @SearchApiProcessor(
 *   id = "exclude_assessment_solutions",
 *   label = @Translation("Assessment group processor"),
 *   description = @Translation("Denies index to solution of the assessments collector group and the group itself."),
 *   stages = {
 *     "alter_items" = 0,
 *   },
 * )
 */
class ExcludeAssessmentSolutionsProcessor extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() == 'node') {
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
      if ($object instanceof SolutionInterface) {
        $group = $object->getCollection();
        if ($group->uuid() === AssessmentInterface::SOLUTION_COLLECTOR) {
          unset($items[$item_id]);
        }
      }

      if ($object instanceof CollectionInterface) {
        // Exclude the collection itself.
        if ($object->uuid() === AssessmentInterface::SOLUTION_COLLECTOR) {
          unset($items[$item_id]);
        }
      }
    }
  }

}
