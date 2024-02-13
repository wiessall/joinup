<?php

declare(strict_types=1);

namespace Drupal\joinup_publication_date\Plugin\Field\FieldType;

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
