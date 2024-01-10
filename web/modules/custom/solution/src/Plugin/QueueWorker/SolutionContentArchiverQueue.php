<?php

declare(strict_types = 1);

namespace Drupal\solution\Plugin\QueueWorker;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\asset_distribution\Entity\AssetDistributionInterface;
use Drupal\asset_release\Entity\AssetReleaseInterface;
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
    AssetReleaseInterface::class,
    AssetDistributionInterface::class,
    CustomPageInterface::class,
    CommunityContentInterface::class,
  ];

  /**
   * Machine name of the target state.
   *
   * @const string
   */
  private const TARGET_STATE = 'archived';

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
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel of solutions.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
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
      $container->get('account_switcher'),
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
        "Queue item processing failed: item is invalid. Got '%id'",
        ['%id' => Variable::export($data)],
      );
      return;
    }

    $context = ['%id' => $data['user'], '%actor' => $data['solution']];
    $solution = $this->entityTypeManager->getStorage('rdf_entity')->load($data['solution']);
    if (is_null($solution)) {
      // Log and skip processing.
      $this->logger->warning(
        "Cannot archive entities of item with ID '%id': solution does not exist anymore.",
        $context,
      );
      return;
    }
    if (!$solution instanceof SolutionInterface) {
      // Log and skip processing.
      $this->logger->error(
        "Cannot archive entities of item with ID '%id': item is not a solution.",
        $context,
      );
      return;
    }

    $actor = $this->entityTypeManager->getStorage('user')->load($data['user']);
    if (!$actor instanceof UserInterface) {
      // Log and skip processing.
      $this->logger->error(
        "Cannot archive entities of item with ID '%id': actor account with ID '%actor' is missing.",
        $context,
      );
      return;
    }

    $entitiesToArchive = $this->getSolutionGroupContent($solution);
    $this->accountSwitcher->switchTo($actor);
    foreach ($entitiesToArchive as $key => $entity) {
      if (
        !$entity instanceof EntityWorkflowStateInterface ||
        !$entity->isTargetWorkflowStateAllowed(static::TARGET_STATE)
      ) {
        // Continue silently.
        continue;
      }

      $entity->setWorkflowState(static::TARGET_STATE)->save();
      $entitiesArchived[$key] = $entity;
    }
    $this->accountSwitcher->switchBack();
  }

  /**
   * Returns group content of the solution which should be archived.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $solution
   *   The solution.
   *
   * @return array<\Drupal\Core\Entity\ContentEntityInterface>
   *   Array of entity instances to archive belonging to the provided solution.
   */
  public function getSolutionGroupContent(GroupInterface $solution): array {
    $idsPerTypeAndBundle = $solution->getGroupContentIds();
    $entities = [];
    foreach ($idsPerTypeAndBundle as $entityType => $idsPerBundle) {
      $entityIds = array_merge(...array_values($idsPerBundle));
      $entities = array_merge(
        $entities,
        array_values($this->entityTypeManager->getStorage($entityType)->loadMultiple($entityIds))
      );
    }

    // Filter out content which shouldn't be archived.
    return array_filter(
      $entities,
      function (EntityInterface $entity): bool {
        foreach (static::ENTITY_CLASSES_TO_ARCHIVE as $class) {
          if (is_a($entity, $class)) {
            return TRUE;
          }
        }
        return FALSE;
      }
    );
  }

}
