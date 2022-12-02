<?php

declare(strict_types = 1);

namespace Drupal\joinup_publication_date\Plugin\Field\FieldType;

use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Field\ChangedFieldItemList;

/**
 * Overrides a item list class for publication date fields.
 */
class PublicationDateFieldItemList extends ChangedFieldItemList {

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    if ($this->isEmpty()) {
      $entity = $this->getEntity();
      if ($entity instanceof EntityPublishedInterface && $entity->isPublished()) {
        $this->set(0, [
          'value' => \Drupal::time()->getRequestTime(),
          'published_at_or_now' => \Drupal::time()->getRequestTime(),
          'published_at_or_created' => \Drupal::time()->getRequestTime(),
          'published_at_or_updated' => \Drupal::time()->getRequestTime(),
        ]);
      }
      if ($entity->isNew()) {
        $this->set(0, ['published_at_or_created' => \Drupal::time()->getRequestTime()]);
      }
      $this->set(0, ['published_at_or_updated' => \Drupal::time()->getRequestTime()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __get($property_name) {
    if ($this->isEmpty()) {
      $this->set(0, ['value' => NULL]);
    }
    return parent::__get($property_name);
  }

}
