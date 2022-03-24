<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides permissions for RDF meta entities.
 */
class RdfMetaEntityPermissionProvider implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new permissions provider instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * Returns a list of permissions.
   *
   * @return array
   *   A list of permissions.
   */
  public function getPermissions(): array {
    $storage = $this->entityTypeManager->getStorage('rdf_meta_entity_type');
    $permissions = [];
    foreach ($storage->loadMultiple() as $rdf_meta_entity_type_id => $rdf_meta_entity_type) {
      $args = ['%type' => $rdf_meta_entity_type->label()];
      $permissions += [
        "create {$rdf_meta_entity_type_id} rdf-meta-entity" => [
          'title' => $this->t('%type: Create new meta-entity', $args),
        ],
        "view {$rdf_meta_entity_type_id} rdf-meta-entity" => [
          'title' => $this->t('%type: View meta-entity', $args),
        ],
        "update {$rdf_meta_entity_type_id} rdf-meta-entity" => [
          'title' => $this->t('%type: Edit meta-entity', $args),
        ],
        "delete {$rdf_meta_entity_type_id} rdf-meta-entity" => [
          'title' => $this->t('%type: Delete meta-entity', $args),
        ],
      ];
    }
    return $permissions;
  }

}
