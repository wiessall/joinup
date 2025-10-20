<?php

declare(strict_types=1);

namespace Drupal\joinup_publication_date\Entity;

use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Blanket implementation for EntityPublicationDateInterface.
 *
 * This will return the value set by the Publication Date module if available.
 *
 * @see \Drupal\joinup_publication_date\Entity\EntityPublicationTimeInterface
 */
trait EntityPublicationTimeTrait {

  /**
   * {@inheritdoc}
   */
  public function getPublicationTime(): ?int {
    if (!$this->hasField('published_at')) {
      return NULL;
    }
    // Check if we have a publication date set by the Publication Date module.
    if ($this->getPublicationDateProvider() !== 'publication_date') {
      return NULL;
    }
    if ($this->get('published_at')->isEmpty() || empty($this->get('published_at')->value) || is_null($this->get('published_at')->value)) {
      return NULL;
    }

    return (int) $this->get('published_at')->value;
  }

  /**
   * Returns the name of the provider of this field.
   *
   * @return string|null
   *   The name of the provider of this field.
   */
  protected function getPublicationDateProvider(): ?string {
    $field_definition = $this->getFieldDefinition('published_at');
    return $field_definition instanceof BaseFieldDefinition ?
      $field_definition->getProvider() :
      $field_definition->getFieldStorageDefinition()->getProvider();
  }

}
