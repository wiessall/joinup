<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
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

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', ?AccountInterface $account = NULL, $return_as_object = FALSE): bool|AccessResultInterface {
    // Prevent translation view access.
    // OSS Solutions are storing translations, but we do not want to expose the
    // corresponding pages in the frontend, only the default translation.
    /* @see content_translation_page_attachments */
    if ($operation === 'view' && !$this->isDefaultTranslation()) {
      return $return_as_object ? AccessResult::forbidden() : FALSE;
    }

    return parent::access($operation, $account, $return_as_object);
  }

}
