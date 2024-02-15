<?php

declare(strict_types=1);

namespace Drupal\rdf_sync\Drush\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rdf_sync\Model\SyncMode;
use Drupal\rdf_sync\RdfSyncSynchronizer;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush commands used to synchronize entities with the RDF backend.
 */
class RdfSyncCommands extends DrushCommands {

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncSynchronizer $synchronizer
   *   The RDF Sync synchronization service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   The entity type bundle info service.
   */
  public function __construct(
    protected RdfSyncSynchronizer $synchronizer,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('rdf_sync.synchronizer'),
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
    );
  }

  /**
   * Set the synchronization mode.
   */
  #[CLI\Command(name: 'rdf_sync:mode')]
  #[CLI\Argument(name: 'mode', description: 'Synchronization mode: delayed, instant or none')]
  public function setSyncMode(string $mode): void {
    $this->synchronizer->setSyncMode(SyncMode::from($mode));
    $this->logger()->notice(dt("Synchronization mode set to '!mode'", ['!mode' => $mode]));
  }

  /**
   * Validates the rdf_sync:mode command argument.
   */
  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'rdf_sync:mode')]
  public function validateSetSyncMode(CommandData $commandData): void {
    if (!SyncMode::tryFrom($commandData->input()->getArgument('mode'))) {
      throw new \InvalidArgumentException("Invalid mode. Should be 'delayed', 'instant' or 'none'");
    }
  }

  /**
   * Synchronize the RDF backend for a given content type.
   */
  #[CLI\Command(name: 'rdf_sync:synchronize')]
  #[CLI\Argument(name: 'entityTypeId', description: 'The entity type (e.g., node, taxonomy_term')]
  #[CLI\Option(name: 'bundle', description: 'Comma delimited list of bundles to limit on')]
  public function synchronize(string $entityTypeId, array $options = [
    'bundle' => self::REQ,
  ]): void {
    $entityType = $this->entityTypeManager->getDefinition($entityTypeId);

    $query = $this->entityTypeManager->getStorage($entityTypeId)->getQuery()->accessCheck(FALSE);

    // If --bundle option is passed, narrow the entities list.
    if ($options['bundle']) {
      if ($entityType->hasKey('bundle')) {
        $bundleKey = $entityType->getKey('bundle');
        $query->condition($bundleKey, $options['bundle'], 'IN');
      }
    }

    // Only sync published content.
    if ($entityType->entityClassImplements(EntityPublishedInterface::class) && $entityType->hasKey('published')) {
      $publishedKey = $entityType->getKey('published');
      $query->condition($publishedKey, TRUE);
    }
    $ids = array_values($query->execute());

    $batchBuilder = (new BatchBuilder())->addOperation([static::class, 'init'], [count($ids)]);
    foreach (array_chunk($ids, 50) as $queuedIds) {
      $batchBuilder->addOperation([static::class, 'synchronizeBatch'], [$entityTypeId, $queuedIds]);
    }
    batch_set($batchBuilder->toArray());
    drush_backend_batch_process();
  }

  /**
   * Validates the rdf_sync:synchronize command argument and options.
   */
  #[CLI\Hook(type: HookManager::ARGUMENT_VALIDATOR, target: 'rdf_sync:synchronize')]
  public function validateSynchronize(CommandData $commandData): void {
    $entityTypeId = $commandData->input()->getArgument('entityTypeId');
    if (!$this->entityTypeManager->getDefinition($entityTypeId, FALSE)) {
      throw new \InvalidArgumentException('Invalid entity type ID');
    }

    $passedBundles = StringUtils::csvToArray($commandData->input()->getOption('bundle'));
    $availableBundles = array_keys($this->entityTypeBundleInfo->getBundleInfo($entityTypeId));
    if ($diff = array_diff($passedBundles, $availableBundles)) {
      throw new \InvalidArgumentException('Invalid bundles: ' . implode(', ', $diff));
    }
    $commandData->input()->setOption('bundle', $passedBundles);
  }

  /**
   * Initiates the batch process.
   *
   * @param int $total
   *   Total number of entities to be synchronized.
   * @param array $context
   *   The batch process context.
   */
  public static function init(int $total, array &$context): void {
    $context['results']['total'] = $total;
    $context['results']['progress'] = 0;
  }

  /**
   * Synchronizes one batch.
   *
   * @param string $entityTypeId
   *   The entity type ID of the entities being synchronized.
   * @param int[]|string[] $ids
   *   A list of entity IDs.
   * @param array $context
   *   The batch process context.
   */
  public static function synchronizeBatch(string $entityTypeId, array $ids, array &$context): void {
    $synchronizer = \Drupal::getContainer()->get('rdf_sync.synchronizer');
    $storage = \Drupal::entityTypeManager()->getStorage($entityTypeId);
    /** @var \Drupal\rdf_sync\Entity\RdfSyncEntityInterface[] $entities */
    $entities = $storage->loadMultiple($ids);
    $synchronizer->synchronize($entities, 'update', SyncMode::INSTANT);
    $context['results']['progress'] += count($entities);
    $context['message'] = "Synchronized {$context['results']['progress']} out of {$context['results']['total']} terms";
  }

}
