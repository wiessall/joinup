<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Interface for the RdfMetaEntity class.
 */
interface RdfMetaEntityInterface extends MetaEntityInterface {

  /**
   * Loads or, if doesn't exists, creates a meta entity given a target entity.
   *
   * @param string $rdf_meta_entity_type
   *   The meta entity type to be loaded or created.
   * @param \Drupal\Core\Entity\ContentEntityInterface $target_entity
   *   The target content entity.
   *
   * @return \Drupal\rdf_meta_entity\Entity\RdfMetaEntityInterface
   *   A meta entity.
   */
  public static function loadOrCreate(string $rdf_meta_entity_type, ContentEntityInterface $target_entity): RdfMetaEntityInterface;

}
