<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for RDF export.
 */
class RdfExportLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The RDF Sync mapper service.
   */
  protected RdfSyncMapper $rdfSyncMapper;

  /**
   * Creates an DynamicLocalTasks object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RdfSyncMapper $mapper) {
    $this->entityTypeManager = $entity_type_manager;
    $this->rdfSyncMapper = $mapper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('rdf_sync.mapper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      $canonical = $entity_type->getLinkTemplate('canonical');
      $is_mapped = $this->rdfSyncMapper->entityTypeHasMappings($entity_type->id());
      if (!$canonical || !$is_mapped) {
        continue;
      }

      $this->derivatives["$entity_type_id.joinup_rdf_export_tab"] = [
        'route_name' => "entity.$entity_type_id.joinup_rdf_export",
        'title' => $this->t('Metadata'),
        'base_route' => "entity.$entity_type_id.canonical",
        'weight' => 100,
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
