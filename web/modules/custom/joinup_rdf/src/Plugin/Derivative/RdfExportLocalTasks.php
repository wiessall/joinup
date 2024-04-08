<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
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
   * Creates an DynamicLocalTasks object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): self {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->hasLinkTemplate('canonical')) {
        $this->derivatives["$entity_type_id.joinup_rdf_export_tab"] = [
          'route_name' => "entity.$entity_type_id.joinup_rdf_export",
          'title' => $this->t('Metadata'),
          'base_route' => "entity.$entity_type_id.canonical",
          'weight' => 100,
        ] + $base_plugin_definition;
      }
    }

    return $this->derivatives;
  }

}
