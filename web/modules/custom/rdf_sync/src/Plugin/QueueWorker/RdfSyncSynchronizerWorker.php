<?php

declare(strict_types=1);

namespace Drupal\rdf_sync\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\rdf_sync\RdfSyncSynchronizerTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Process queued RDF synchronizations.
 *
 * @QueueWorker(
 *   id = "rdf_sync_synchronizer",
 *   title = @Translation("Synchronizes the RDF backend"),
 *   cron = {"time" = 30},
 * )
 */
class RdfSyncSynchronizerWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use RdfSyncSynchronizerTrait;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected SerializerInterface $serializer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('serializer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    /** @var array[] $idsByEntityType */
    /** @var string $method */
    extract($data);

    $entities = [];
    foreach ($idsByEntityType as $entityTypeId => $ids) {
      $storage = $this->entityTypeManager->getStorage($entityTypeId);
      $entities = [...$entities, ...array_values($storage->loadMultiple($ids))];
    }

    $this->doSynchronize($entities, $method);
  }

}
