<?php

declare(strict_types = 1);

namespace Drupal\solution\Plugin\QueueWorker;

use Drupal\asset_distribution\Entity\AssetDistributionInterface;
use Drupal\asset_release\Entity\AssetReleaseInterface;
use Drupal\Component\Utility\Variable;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Archives entities related to an archived solution.
 *
 * @QueueWorker(
 *   id = "solution_archive_related_content",
 *   title = @Translation("Archive solution's related content"),
 *   cron = {"time" = 60},
 * )
 */
class SolutionContentArchiverQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  private const ENTITY_CLASSES_TO_ARCHIVE = [
    AssetReleaseInterface::class => 'draft',
    AssetDistributionInterface::class => 'draft',
    CustomPageInterface::class => 'archived',
    CommunityContentInterface::class => 'archived',
  ];

  /**
   * Constructs a new SolutionContentArchiverQueue instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
    protected QueueFactory $queueFactory,
    protected AccountSwitcherInterface $accountSwitcher,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.channel.solution'),
      $container->get('queue'),
      $container->get('account_switcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (
      !is_array($data) ||
      !isset($data['user']) ||
      !isset($data['solution'])
    ) {
      $this->logger->error(
        "Archival of solution's related content failed: The queued ID is not a string: '%id'",
        ['%id' => Variable::export($data)],
      );
      return;
    }


    $solution = $this->entityTypeManager->getStorage('rdf_entity')->load($data['solution']);

    $context = ['%id' => $data];
    if (is_null($solution)) {
      // Log and skip processing.
      $this->logger->warning(
        "Cannot archive entities of item with id '%id': solution does not exist anymore.",
        $context,
      );
      return;
    }
    if (!$solution instanceof SolutionInterface) {
      // Log and skip processing.
      $this->logger->error(
        "Cannot archive entities of item with id '%id': item is not a solution.",
        $context,
      );
      return;
    }

    $actionPerformerUser = $this->entityTypeManager->getStorage('user')->load($data['user']);
    \assert($actionPerformerUser instanceof UserInterface);

    $entitiesToArchive = $this->getSolutionGroupContent($solution);
    $entitiesArchived = [];
    $this->accountSwitcher->switchTo($actionPerformerUser);
    foreach ($entitiesToArchive as $key => $entity) {
      $targetState = static::getTargetState($entity);
      if (
        !$entity instanceof EntityWorkflowStateInterface ||
        !$targetState ||
        !$entity->isTargetWorkflowStateAllowed($targetState)
      ) {
        $entitiesNonArchivable[] = $entity;
        continue;
      }

      if ($targetState && $entity->isTargetWorkflowStateAllowed($targetState)) {
        $entity->setWorkflowState($targetState)->save();
        $entitiesArchived[$key] = $entity;
      }
    }
    $this->accountSwitcher->switchBack();

    $d = 1;
  }

  public function getSolutionGroupContent(GroupInterface $solution): array {
    $idsPerTypeAndBundle = $solution->getGroupContentIds();
    $entities = [];
    foreach ($idsPerTypeAndBundle as $entityType => $idsPerBundle) {
      $entityIds = array_merge(...array_values($idsPerBundle));
      $entities = array_merge(
        $entities,
        $this->entityTypeManager
          ->getStorage($entityType)
          ->loadMultiple($entityIds)
      );
    }

    return $entities;
  }

  protected static function getTargetState(EntityInterface $entity): ?string {
    foreach (self::ENTITY_CLASSES_TO_ARCHIVE as $className => $state) {
      if (is_subclass_of($entity,  $className, FALSE)) {
        return $state;
      }
    }
    return NULL;
  }

}
