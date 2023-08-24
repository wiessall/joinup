<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Entity;

use Drupal\eu_oss_catalogue\Entity\OssSolution;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;

/**
 * Joinup OSS solution.
 */
class JoinupOssSolution extends OssSolution implements DescriptionInterface {

  use DescriptionTrait;
  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'oss_short_description';
  }

  /**
   * Gets entity categories.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   Entity terms.
   */
  public function getCategories(): array {
    return $this->getReferencedEntities('oss_categories');
  }

}
