<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\DisplayVariant;

use Drupal\Core\Display\PageVariantInterface;
use Drupal\block\Plugin\DisplayVariant\BlockPageVariant;

/**
 * Page display variant for OSS catalogue.
 *
 * @PageDisplayVariant(
 *   id = "joinup_oss_catalogue_page",
 *   admin_label = @Translation("OSS catalogue page"),
 * )
 */
class JoinupOssCataloguePageDisplayVariant extends BlockPageVariant {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'group' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setMainContent(array $main_content): PageVariantInterface {
    $this->mainContent = views_embed_view('joinup_oss_catalogue');

    return $this;
  }

}
