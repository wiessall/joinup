<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Entity;

use Drupal\eu_oss_catalogue\Entity\OssSolution;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_bundle_class\DescriptionTrait;

class JoinupOssSolution extends OssSolution implements DescriptionInterface {

  use DescriptionTrait;

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'oss_short_description';
  }

}
