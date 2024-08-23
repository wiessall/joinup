<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\OgGroupResolver;

use Drupal\joinup_oss_catalogue\Entity\JoinupOssSolution;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\OgRouteGroupResolverBase;

/**
 * Resolves the group for EU OSS catalogue search route.
 *
 * @OgGroupResolver(
 *   id = "joinup_oss_catalogue_routes",
 *   label = "Routes related to EU OSS catalogue",
 * )
 */
class JoinupOssCatalogueGroupResolver extends OgRouteGroupResolverBase {

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    if (!$this->isOssContent()) {
      return;
    }

    /** @var \Drupal\collection\Entity\CollectionInterface $ossCatalogueGroup */
    $ossCatalogueGroup = $this->entityTypeManager->getStorage('node')
      ->load(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID);
    if ($ossCatalogueGroup) {
      $collection->addGroup($ossCatalogueGroup, ['route']);
      $this->stopPropagation();
    }
  }

  /**
   * Checks if it is Oss content.
   *
   * @return bool
   *   True if it is Oss content.
   */
  protected function isOssContent(): bool {
    if ($this->routeMatch->getRouteName() === 'view.search_oss_catalogue.search') {
      return TRUE;
    }

    $entity = $this->getContentEntity();
    if ($entity instanceof JoinupOssSolution) {
      return TRUE;
    }

    return FALSE;
  }

}
