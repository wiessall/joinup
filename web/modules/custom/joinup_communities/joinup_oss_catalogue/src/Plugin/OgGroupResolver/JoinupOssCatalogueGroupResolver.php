<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\OgGroupResolver;

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
    if ($this->routeMatch->getRouteName() !== 'view.search_oss_catalogue.search') {
      return;
    }

    /** @var \Drupal\collection\Entity\CollectionInterface $ossCatalogueGroup */
    $ossCatalogueGroup = $this->entityTypeManager->getStorage('rdf_entity')
      ->load(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID);
    if ($ossCatalogueGroup) {
      $collection->addGroup($ossCatalogueGroup, ['route']);
      $this->stopPropagation();
    }
  }

}
