<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Entity;

use Drupal\eu_oss_catalogue\Entity\OssSolution;
use Drupal\file\FileInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_bundle_class\LogoTrait;

/**
 * Joinup OSS solution.
 */
class JoinupOssSolution extends OssSolution implements DescriptionInterface, LogoInterface {

  use DescriptionTrait;
  use LogoTrait;
  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'oss__description';
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

  /**
   * {@inheritdoc}
   */
  public function getLogoFieldName(): string {
    return 'oss_logo';
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoAsFile(): ?FileInterface {
    return NULL;
  }

}
