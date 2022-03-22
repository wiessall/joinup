<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity;

use Drupal\meta_entity\MetaEntityRepository;

/**
 * Provides a default implementation for 'rdf_meta_entity.repository' service.
 */
class RdfMetaEntityRepository extends MetaEntityRepository {

  /**
   * {@inheritdoc}
   */
  public function getMetaEntityContentEntityId(): string {
    return 'rdf_meta_entity';
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaEntityTypeConfigEntityId(): string {
    return 'rdf_meta_entity_type';
  }

}
