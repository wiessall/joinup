<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Plugin\search_api\processor;

use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\SearchApiException;

/**
 * Attaches necessary data from the solution to the release.
 *
 * This should be set in the index configuration to run before the
 * AddAggregatedTopicHierarchy processor.
 *
 * @see \Drupal\joinup_search\Plugin\search_api\processor\AddAggregatedTopicHierarchy
 *
 * @SearchApiProcessor(
 *   id = "release_solution_data",
 *   label = @Translation("Release solution data"),
 *   description = @Translation("Attaches necessary data from the solution to the release."),
 *   stages = {
 *     "preprocess_index" = -60,
 *   },
 * )
 */
class ReleaseSolutionData extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      try {
        /** @var \Drupal\search_api\Item\ItemInterface $item */
        $entity = $item->getOriginalObject()->getValue();
      }
      catch (SearchApiException) {
        return;
      }

      if (!$entity instanceof ReleaseInterface) {
        return;
      }

      $solution = $entity->get('solution_data')->entity;
      if ((!$solution instanceof SolutionInterface) || $solution->get('field_topic')->isEmpty()) {
        return;
      }

      $field = $this->getIndex()->getField('topic');
      $field->addValue($solution->get('field_topic')->target_id);
      $item->setField('topic', $field);

      $contactLabel = $solution->get('contact')->entity?->label() ?? '';
      $ownerLabel = $solution->get('owner')->entity?->label() ?? '';
      // Include also the contact & owner labels of the parent solution.
      $description = $this->getIndex()->getField('description_processed');
      $description->addValue($contactLabel)->addValue($ownerLabel);
      $item->setField('description_processed', $description);
    }
  }

}
