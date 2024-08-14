<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Entity;

/**
 * Entity with a permalink.
 */
interface PermalinkInterface {

  /**
   * Returns the permalink for the entity.
   *
   * @return string|null
   *   The permalink if it exists.
   */
  public function getPermalink(): ?string;

}
