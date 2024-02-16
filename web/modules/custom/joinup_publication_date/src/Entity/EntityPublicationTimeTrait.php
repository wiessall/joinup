<?php

declare(strict_types=1);

namespace Drupal\joinup_publication_date\Entity;

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
    // Check if we have a publication date set by the Publication Date module.
    if (!$this->hasField('published_at') || $this->getFieldDefinition('published_at')->getProvider() !== 'publication_date') {
      return NULL;
    }

    if ($this->get('published_at')->isEmpty() || empty($this->get('published_at')->value) || is_null($this->get('published_at')->value)) {
      return NULL;
    }

    return (int) $this->get('published_at')->value;
  }

}
