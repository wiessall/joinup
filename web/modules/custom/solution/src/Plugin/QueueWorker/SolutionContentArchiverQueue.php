<?php

declare(strict_types=1);

namespace Drupal\solution\Plugin\QueueWorker;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\asset_distribution\Entity\AssetDistributionInterface;
use Drupal\asset_release\Entity\AssetReleaseInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\joinup_workflow\WorkflowStateChangeMessageTrait;
use Drupal\solution\Entity\SolutionInterface;
use Drupal\solution\Transition;
use Drupal\sparql_entity_storage\SparqlEntityStorageInterface;
use Drupal\sparql_entity_storage\SparqlGraphInterface;
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

  use StringTranslationTrait;
  use WorkflowStateChangeMessageTrait;

  private const ENTITY_CLASSES_TO_ARCHIVE = [
    AssetReleaseInterface::class,
    AssetDistributionInterface::class,
    CustomPageInterface::class,
    CommunityContentInterface::class,
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
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel of solutions.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The account switcher service.
   * @param \Drupal\joinup_workflow\WorkflowHelperInterface $workflowHelper
   *   The workflow helper service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
    protected AccountSwitcherInterface $accountSwitcher,
    protected WorkflowHelperInterface $workflowHelper,
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
    $plugin_definition,
  ): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.channel.solution'),
      $container->get('account_switcher'),
      $container->get('joinup_workflow.workflow_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    if (!$this->queueItemIsValid($data)) {
      return;
    }
    \assert(is_array($data));
    if (!$actor = $this->getActorFromItem($data)) {
      return;
    }

    if (!$solution = $this->getParentSolutionFromItem($data)) {
      return;
    }

    $this->accountSwitcher->switchTo($actor);
    try {
      $this->performOperation($solution, $data['transition']);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Returns group content of the solution which should be changed.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $solution
   *   The solution.
   *
   * @return array<\Drupal\Core\Entity\ContentEntityInterface>
   *   Array of entity instances to change belonging to the provided solution.
   */
  protected function getSolutionGroupContent(GroupInterface $solution): array {
    $idsPerTypeAndBundle = $solution->getGroupContentIds();
    $entities = [];
    foreach ($idsPerTypeAndBundle as $entityType => $idsPerBundle) {
      $entityIds = array_merge(...array_values($idsPerBundle));
      $entities = array_merge(
        $entities,
        array_values($this->entityTypeManager->getStorage($entityType)->loadMultiple($entityIds))
      );
    }

    // Filter out content which shouldn't be changed.
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

  /**
   * Performs the archive transition on the sub content of the solution.
   *
   * @param \Drupal\solution\Entity\SolutionInterface $solution
   *   The solution whose sub-contents we want to archive.
   * @param \Drupal\solution\Transition $transition
   *   The transition to apply.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function performOperation(SolutionInterface $solution, Transition $transition): void {
    foreach ($this->getSolutionGroupContent($solution) as $entity) {
      if (!$entity instanceof ArchivableEntityInterface) {
        // Continue silently.
        continue;
      }

      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      if (
        $storage instanceof SparqlEntityStorageInterface &&
        $entity->get('graph')->getString() !== SparqlGraphInterface::DEFAULT
      ) {
        // Load from the default (assuming "published") graph, because we want
        // to archive only the publicly accessible instance.
        $entity = $storage->load($entity->id(), [SparqlGraphInterface::DEFAULT]) ?? $entity;
      }

      // Current user cannot change the state to "archived" or "unarchived"
      // state.
      $allowedTransitions = $entity->getWorkflow()->getAllowedTransitions($entity->getWorkflowState(), $entity);
      if (!array_key_exists($transition->getMachineName(), $allowedTransitions)) {
        continue;
      }
      $targetState = $allowedTransitions[$transition->getMachineName()]->getToState()->getId();
      // No need to archive unpublished or already archived entities.
      if (
        $entity->getWorkflowState() === $targetState ||
        ($entity instanceof EntityPublishedInterface && !$entity->isPublished())
      ) {
        // Continue silently.
        continue;
      }

      $entity->skip_notification = TRUE;
      try {
        $newState = match ($transition) {
          Transition::ARCHIVE => 'archived',
          Transition::UNARCIVE =>'published',
        };
        $revisionLogMessage = $this->workflowStateChangeRevisionLogMessage(
          $entity,
          $newState,
          $this->t("'@plugin-id' queue", [
            '@plugin-id' => $this->getPluginId(),
          ])
        );
        match ($transition) {
          // '@plugin-id' queue
          Transition::ARCHIVE => $this->workflowHelper->archiveEntity($entity, $revisionLogMessage),
          Transition::UNARCIVE => $this->workflowHelper->unarchiveEntity($entity, $revisionLogMessage),
        };
      }
      catch (\Exception $exception) {
        $this->logger->warning($exception->getMessage());
      }
    }

    if ($transition === Transition::ARCHIVE) {
      joinup_notification_dispatch_notification('update', NotificationEvents::RDF_ENTITY_ARCHIVE_COMPLETED, $solution);
    }
  }

  /**
   * Determines whether the queue item is valid or not.
   *
   * @param mixed $data
   *   The queue item.
   *
   * @return bool
   *   Returns FALSE if the queue item is invalid, or TRUE if:
   *   - The item is an array.
   *   - It has a "user" key with integer or string value.
   *   - It has a "solution" key with string value.
   *   - It has a "transition" key with \Drupal\solution\Transition value.
   */
  protected function queueItemIsValid(mixed $data): bool {
    if (
      !is_array($data) ||
      !isset($data['user']) ||
      !isset($data['solution']) ||
      !isset($data['transition'])
    ) {
      $this->logger->error(
        "Queue item processing failed: item is invalid. Got '%item'",
        ['%item' => Variable::export($data)],
      );
      return FALSE;
    }

    [
      'user' => $uid,
      'solution' => $solutionId,
      'transition' => $transition,
    ] = $data;
    if (
      (!is_int($uid) && !is_string($uid)) ||
      (!is_string($solutionId))
    ) {
      $this->logger->error(
        "Queue item processing failed: user ID and solution ID must be string. Got '%item'",
        ['%item' => Variable::export($data)],
      );
      return FALSE;
    }

    if (!$transition instanceof Transition) {
      $this->logger->error(
        "Queue item processing failed: Transition invalid. Got '%item'",
        ['%item' => Variable::export($data)],
      );
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Instantiates the solution instance from the provided queue item.
   *
   * @param array $data
   *   The queue item array with string value at the "solution" key.
   *
   * @return \Drupal\solution\Entity\SolutionInterface|null
   *   The solution entity if it exists.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getParentSolutionFromItem(array $data): ?SolutionInterface {
    $context = ['%solutionId' => $data['solution']];
    $solution = $this->entityTypeManager->getStorage('rdf_entity')->load($data['solution']);
    if (is_null($solution)) {
      // Log and skip processing.
      $this->logger->warning(
        "Cannot archive entities of item with ID '%id': solution does not exist anymore.",
        $context,
      );
      return NULL;
    }
    if (!$solution instanceof SolutionInterface) {
      // Log and skip processing.
      $this->logger->error(
        "Cannot archive entities of item with ID '%id': item is not a solution.",
        $context,
      );
      return NULL;
    }
    return $solution;
  }

  /**
   * Instantiates the solution instance from the provided queue item.
   *
   * @param array $data
   *   The queue item array with string or integer value at the "user" key.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user account which will be used as the actor of the state transition,
   *   or NULL if it does not exist anymore.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getActorFromItem(array $data): ?UserInterface {
    $actor = $this->entityTypeManager->getStorage('user')->load($data['user']);
    if (!$actor instanceof UserInterface) {
      // Log and skip processing.
      $this->logger->error(
        "Cannot archive entities of item with ID '%solutionId': actor account with ID '%actor' is missing.",
        [
          '%solutionId' => $data['solution'],
          '%actor' => $data['user'],
        ],
      );
      return NULL;
    }
    return $actor;
  }

}
