<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\SearchApiException;

/**
 * Attaches necessary data from the solution to the release.
 *
 * This should be set in the index configuration to run before the
 * AddAggregatedTopicHierarchy processor.
 *
 * @see \Drupal\joinup_search\Plugin\search_api\processor\AddAggregatedTopicHierarchy
 */
#[SearchApiProcessor(
  id: "release_solution_data",
  label: new TranslatableMarkup("Release solution data"),
  description: new TranslatableMarkup("Attaches necessary data from the solution to the release."),
  stages: [
    "preprocess_index" => -60,
  ],
)]
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
        continue;
      }

      if (!$entity instanceof ReleaseInterface) {
        continue;
      }

      $langcode = $entity->language()->getId();

      $solutions = $entity->get('solution_data')->referencedEntities();
      foreach ($solutions as $solution) {
        $solution = $this->getTranslation($solution, $langcode);

        // Collect topics from solution.
        $field = $item->getField('topic');
        foreach ($solution->get('field_topic')->referencedEntities() as $term) {
          $field->addValue($term->id());
        }

        // Include also the contact & owner labels of the parent solution.
        $description = $item->getField('description');
        foreach ($solution->get('contact')->referencedEntities() as $contact) {
          $contact = $this->getTranslation($contact, $langcode);
          $description->addValue($contact->label());
        }

        foreach ($solution->get('owner')->referencedEntities() as $owner) {
          $owner = $this->getTranslation($owner, $langcode);
          $description->addValue($owner->label());
        }
      }
    }
  }

  /**
   * Returns the given entity's translation, if it exists.
   */
  private function getTranslation(EntityInterface $entity, string $langcode): EntityInterface {
    return $entity instanceof TranslatableInterface && $entity->hasTranslation($langcode)
      ? $entity->getTranslation($langcode)
      : $entity;
  }

}
