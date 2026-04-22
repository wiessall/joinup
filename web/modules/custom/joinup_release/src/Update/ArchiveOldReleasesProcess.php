<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Update;

use Drupal\joinup_core\Update\UpdateProcessInterface;

/**
 * Archives releases that have not been updated in more than 2 years.
 */
class ArchiveOldReleasesProcess implements UpdateProcessInterface {

  /**
   * {@inheritdoc}
   */
  public function process(array &$sandbox): void {
    // @todo Implement archiving of releases older than 2 years in ISAICP-XXXX.
  }

}