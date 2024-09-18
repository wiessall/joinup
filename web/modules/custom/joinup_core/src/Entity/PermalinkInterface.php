<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Entity;

use Drupal\Core\Url;

/**
 * Entity with a permalink.
 */
interface PermalinkInterface {

  /**
   * Returns the permalink for the entity.
   *
   * @return \Drupal\Core\Url|null
   *   The permalink if it exists.
   */
  public function getPermalink(): ?Url;

}
