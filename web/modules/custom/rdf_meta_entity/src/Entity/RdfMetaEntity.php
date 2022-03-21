<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\sparql_entity_storage\Entity\SparqlMapping;
use Drupal\sparql_entity_storage\SparqlGraphInterface;

/**
 * Defines the meta entity content entity class with SPARQL storage backend.
 *
 * @ContentEntityType(
 *   id = "rdf_meta_entity",
 *   label = @Translation("Rdf meta entity"),
 *   label_collection = @Translation("Meta entities"),
 *   bundle_label = @Translation("Meta entity type"),
 *   handlers = {
 *     "storage" = "\Drupal\sparql_entity_storage\SparqlEntityStorage",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   bundle_entity_type = "rdf_meta_entity_type",
 *   admin_permission = "administer rdf meta entity",
 *   field_ui_base_route = "entity.rdf_meta_entity_type.edit_form",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "rid",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 * )
 */
class RdfMetaEntity extends MetaEntity implements MetaEntityInterface {

  /**
   * Entity bundle.
   *
   * @var string
   */
  protected $rid;

  /**
   * Get the bundle of the entity.
   */
  public function getRid() {
    return $this->get('rid')->value;
  }

  /**
   * Get the bundle from the entity.
   */
  public function getType() {
    return $this->bundle();
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->label();
  }

  /**
   * {@inheritdoc}
   *
   * @todo Use interface.
   */
  public function setName(string $name): RdfMetaEntity {
    $this->set('label', $name);
    return $this;
  }

  /**
   * Get weight.
   *
   * @todo This should be removed rdf terms have their own
   * proper implementation.
   */
  public function getWeight() {
    return 0;
  }

  /**
   * Returns whether the bundle of the entity has a mapping for the uid key.
   *
   * @return bool
   *   Whether the entity bundle has a value for the uid key mapping.
   */
  protected function hasUidMapping() {
    return $this->hasFieldMapping('uid', 'target_id');
  }

  /**
   * Returns whether the entity bundle has mapping for a certain field column.
   *
   * @param string $field_name
   *   The field name.
   * @param string $column
   *   The field column. Defaults to 'value'.
   *
   * @return bool
   *   TRUE if the mapping is set, FALSE otherwise.
   */
  protected function hasFieldMapping($field_name, $column = 'value') {
    $mapping = SparqlMapping::loadByName($this->getEntityTypeId(), $this->bundle());
    return $mapping && !empty($mapping->getMapping($field_name, $column)['predicate']);
  }

  /**
   * {@inheritdoc}
   */
  public function isPublished() {
    /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $storage */
    $storage = $this->entityTypeManager()->getStorage($this->getEntityTypeId());
    $published_graph = $storage->getGraphHandler()->getBundleGraphUri($this->getEntityTypeId(), $this->bundle(), SparqlGraphInterface::DEFAULT);
    $entity_graph_name = $this->get('graph')->target_id;
    // If no graph is yet set, get the default graph for the entity.
    if (empty($entity_graph_name)) {
      $entity_graph_name = $storage->getGraphHandler()->getDefaultGraphId($this->getEntityTypeId());
    }
    $entity_graph = $storage->getGraphHandler()->getBundleGraphUri($this->getEntityTypeId(), $this->bundle(), $entity_graph_name);
    return ($entity_graph === $published_graph);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['rid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Rdf Type'))
      ->setDescription(t('The Rdf type of this entity.'))
      ->setSetting('target_type', 'rdf_type');

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function loadOrCreate(string $rdf_meta_entity_type, ContentEntityInterface $target_entity): MetaEntityInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('rdf_meta_entity');
    $ids = $storage->getQuery()
      ->condition('rid', $rdf_meta_entity_type)
      ->condition('target.target_type', $target_entity->getEntityTypeId())
      ->condition('target.target_id', $target_entity->id())
      ->execute();

    if ($ids) {
      $id = reset($ids);
      $rdf_meta_entity = $storage->load($id);
    }
    else {
      $rdf_meta_entity = $storage->create([
        'rid' => $rdf_meta_entity_type,
        'target' => $target_entity,
      ]);
    }

    /** @var \Drupal\rdf_meta_entity\Entity\RdfMetaEntity $rdf_meta_entity */
    return $rdf_meta_entity;
  }

}
