<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Defines the meta entity content entity class with SPARQL storage backend.
 *
 * @ContentEntityType(
 *   id = "rdf_meta_entity",
 *   label = @Translation("RDF meta entity"),
 *   label_collection = @Translation("Meta entities"),
 *   bundle_label = @Translation("Meta entity type"),
 *   handlers = {
 *     "storage" = "\Drupal\sparql_entity_storage\SparqlEntityStorage",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\rdf_meta_entity\RdfMetaEntityAccessControlHandler",
 *   },
 *   bundle_entity_type = "rdf_meta_entity_type",
 *   admin_permission = "administer rdf meta entity",
 *   field_ui_base_route = "entity.rdf_meta_entity_type.edit_form",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class RdfMetaEntity extends MetaEntity implements RdfMetaEntityInterface {

  /**
   * {@inheritdoc}
   */
  public static function loadOrCreate(string $rdf_meta_entity_type, ContentEntityInterface $target_entity): RdfMetaEntityInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('rdf_meta_entity');
    $ids = $storage->getQuery()
      ->condition('type', $rdf_meta_entity_type)
      ->condition('target.target_type', $target_entity->getEntityTypeId())
      ->condition('target.target_id', $target_entity->id())
      ->execute();

    if ($ids) {
      $id = reset($ids);
      $rdf_meta_entity = $storage->load($id);
    }
    else {
      $rdf_meta_entity = $storage->create([
        'type' => $rdf_meta_entity_type,
        'target' => $target_entity,
      ]);
    }

    /** @var \Drupal\rdf_meta_entity\Entity\RdfMetaEntityInterface $rdf_meta_entity */
    return $rdf_meta_entity;
  }

}
