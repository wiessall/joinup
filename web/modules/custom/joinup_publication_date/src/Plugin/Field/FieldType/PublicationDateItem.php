<?php

declare(strict_types=1);

namespace Drupal\joinup_publication_date\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\publication_date\Plugin\Field\FieldType\PublicationDateItem as OriginalPublicationDateItem;

/**
 * Overrides the publication date item class.
 */
class PublicationDateItem extends OriginalPublicationDateItem {

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    $date = \Drupal::time()->getRequestTime();
    $this->setValue(
      [
        'value' => NULL,
        'published_at_or_now' => $date,
        'published_at_or_created' => $date,
        'published_at_or_updated' => $date,
      ], $notify
    );
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['published_at_or_created'] = DataDefinition::create('timestamp')
      ->setLabel(t('Published at or created'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\joinup_publication_date\PublishedAtOrCreatedComputed')
      ->setSetting('source', 'value');

    $properties['published_at_or_updated'] = DataDefinition::create('timestamp')
      ->setLabel(t('Published at or updated'))
      ->setComputed(TRUE)
      ->setClass('\Drupal\joinup_publication_date\PublishedAtOrUpdatedComputed')
      ->setSetting('source', 'value');

    return $properties;
  }

}
