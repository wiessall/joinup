<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity;

use Drupal\meta_entity\MetaEntityPermissionProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides permissions for RDF meta entities.
 */
class RdfMetaEntityPermissionProvider extends MetaEntityPermissionProvider {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      'rdf_meta_entity_type'
    );
  }

}
