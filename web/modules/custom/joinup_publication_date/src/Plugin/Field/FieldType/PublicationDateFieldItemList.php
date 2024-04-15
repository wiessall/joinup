<?php

declare(strict_types=1);

namespace Drupal\joinup_publication_date\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\publication_date\Plugin\Field\FieldType\PublicationDateFieldItemList as OriginalPublicationDateFieldItemList;

/**
 * Overrides an item list class for publication date fields.
 *
 * Drupal Timestamps are expected to never be empty, so we have to return
 * the never-empty computed value for the benefit of display formatters.
 */
class PublicationDateFieldItemList extends OriginalPublicationDateFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function preSave(): void {
    // Call the two lines from \Drupal\Core\Field\FieldItemList::preSave() in
    // order to avoid to call the original parent method but still support core
    // API.
    $this->filterEmptyItems();
    $this->delegateMethod('preSave');

    if ($this->isEmpty() && $entity = $this->getEntity()) {
      // We are universally handling the publication state of the entity in
      // \joinup_workflow_entity_presave(). However, the `::preSave()` method
      // is called before the hook_entity_presave() hook, so we need to
      // handle the publication here. Because the hook is called later, the
      // status of the entity is not a trusted source of information yet.
      if ($entity instanceof EntityWorkflowStateInterface) {
        if (\Drupal::getContainer()
          ->get('joinup_workflow.workflow_helper')
          ->isWorkflowStatePublished(
            $entity->getWorkflowState(),
            $entity->getWorkflow()
          )) {
          $this->set(0, ['value' => \Drupal::time()->getRequestTime()]);
        }
        // In case the entity does not implement a workflow, fallback to the
        // default functionality.
        elseif ($entity instanceof EntityPublishedInterface && $entity->isPublished()) {
          $this->set(0, ['value' => \Drupal::time()->getRequestTime()]);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __get($property_name) {
    if ($property_name === 'published_at_or_created' && $this->isEmpty()) {
      $this->set(0, ['value' => NULL]);
    }
    if ($property_name === 'published_at_or_updated' && $this->isEmpty()) {
      $this->set(0, ['value' => NULL]);
    }
    return parent::__get($property_name);
  }

}
