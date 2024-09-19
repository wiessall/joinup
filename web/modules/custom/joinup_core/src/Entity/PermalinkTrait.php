<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Entity;

use Drupal\Core\Url;

/**
 * Reusable code for classes implementing PermalinkInterface interface.
 *
 * @see \Drupal\joinup_core\Entity\PermalinkInterface
 */
trait PermalinkTrait {

  /**
   * {@inheritdoc}
   */
  public function getPermalink(): ?Url {
    if ($this->hasField('permalink') && !$this->get('permalink')->isEmpty()) {
      return $this->get('permalink')->first()->getUrl();
    }
    return NULL;
  }

}
