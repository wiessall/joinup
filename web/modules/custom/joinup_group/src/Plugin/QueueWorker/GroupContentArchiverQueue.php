<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\QueueWorker;

use Drupal\Component\Utility\Variable;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Transition;
use Drupal\joinup_notification\NotificationEvents;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\joinup_workflow\WorkflowStateChangeMessageTrait;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Archives entities related to an archived group.
 */
#[QueueWorker(
  id: 'group_archive_related_content',
  title: new TranslatableMarkup("Archive group's related content"),
  cron: ['time' => 60],
)]
class GroupContentArchiverQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use WorkflowStateChangeMessageTrait;

  private const array ENTITY_CLASSES_TO_ARCHIVE = [
    ReleaseInterface::class,
    CustomPageInterface::class,
    CommunityContentInterface::class,
    DistributionInterface::class,
  ];

  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly LoggerChannelInterface $logger,
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly WorkflowHelperInterface $workflowHelper,
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
      $container->get('logger.channel.joinup_group'),
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

    if (!$group = $this->getParentGroupFromItem($data)) {
      return;
    }

    $this->accountSwitcher->switchTo($actor);
    try {
      $this->performOperation($group, $data['transition'], $data['motivation'] ?? NULL);
    }
    finally {
      $this->accountSwitcher->switchBack();
    }
  }

  /**
   * Returns group content which should be archived or unarchived.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group.
   *
   * @return array<\Drupal\Core\Entity\ContentEntityInterface>
   *   Array of entity instances belonging to the group that should be changed.
   */
  protected function getGroupContent(GroupInterface $group): array {
    $idsPerTypeAndBundle = $group->getDirectGroupContentIds();
    $entities = [];
    foreach ($idsPerTypeAndBundle as $entityType => $idsPerBundle) {
      $entityIds = array_merge(...array_values($idsPerBundle));
      $entities = array_merge(
        $entities,
        array_values($this->entityTypeManager->getStorage($entityType)->loadMultiple($entityIds))
      );
    }

    // Sub-groups are never archived here; each group manages its own
    // sub-content independently.
    return array_filter(
      $entities,
      function (EntityInterface $entity): bool {
        if ($entity instanceof GroupInterface) {
          return FALSE;
        }
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
   * Performs the archive or unarchive transition on the group's sub-content.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group whose sub-content should be (un)archived.
   * @param \Drupal\joinup_group\Transition $transition
   *   The transition to apply.
   * @param string|null $motivation
   *   The injected motivation if any.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function performOperation(GroupInterface $group, Transition $transition, ?string $motivation = NULL): void {
    foreach ($this->getGroupContent($group) as $entity) {
      if (!$entity instanceof ArchivableEntityInterface) {
        // Continue silently.
        continue;
      }

      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      // Load the published version of the entity if the entity is published.
      if (!$entity->isPublished()) {
        $entity = $storage->load($entity->id()) ?? $entity;
      }

      // Current user cannot change the state to "archived" or "unarchived"
      // state.
      $allowedTransitions = $entity->getWorkflow()->getAllowedTransitions($entity->getWorkflowState(), $entity);
      if (!array_key_exists($transition->value, $allowedTransitions)) {
        continue;
      }
      $targetState = $allowedTransitions[$transition->value]->getToState()->getId();
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
          Transition::Archive => 'archived',
          Transition::Unarchive => 'published',
        };
        $revisionLogMessage = $this->workflowStateChangeRevisionLogMessage(
          $entity,
          $newState,
          $this->t("'@plugin-id' queue", [
            '@plugin-id' => $this->getPluginId(),
          ])
        );
        match ($transition) {
          Transition::Archive => $this->workflowHelper->archiveEntity($entity, $revisionLogMessage),
          Transition::Unarchive => $this->workflowHelper->unarchiveEntity($entity, $revisionLogMessage),
        };
      }
      catch (\Exception $exception) {
        $this->logger->warning($exception->getMessage());
      }
    }

    if ($transition === Transition::Archive) {
      // Motivation is lost after unserialize so re add it back.
      $group->motivation = $motivation;
      joinup_notification_dispatch_notification('update', NotificationEvents::GROUP_ENTITY_ARCHIVE_COMPLETED, $group);
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
   *   - It has a "group" key with string value.
   *   - It has a "transition" key with \Drupal\joinup_group\Transition value.
   */
  protected function queueItemIsValid(mixed $data): bool {
    if (
      !is_array($data) ||
      !isset($data['user']) ||
      !isset($data['group']) ||
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
      'group' => $groupId,
      'transition' => $transition,
    ] = $data;
    if (
      (!is_int($uid) && !is_string($uid)) ||
      (!is_string($groupId))
    ) {
      $this->logger->error(
        "Queue item processing failed: user ID and group ID must be string. Got '%item'",
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
   * Loads the group entity from the provided queue item.
   *
   * @param array $data
   *   The queue item array with string value at the "group" key.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface|null
   *   The group entity if it exists, NULL otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getParentGroupFromItem(array $data): ?GroupInterface {
    $context = ['%groupId' => $data['group']];
    $group = $this->entityTypeManager->getStorage('node')->load($data['group']);
    if (is_null($group)) {
      $this->logger->warning(
        "Cannot archive entities of item with ID '%groupId': group does not exist anymore.",
        $context,
      );
      return NULL;
    }
    if (!$group instanceof GroupInterface) {
      $this->logger->error(
        "Cannot archive entities of item with ID '%groupId': item is not a group.",
        $context,
      );
      return NULL;
    }
    return $group;
  }

  /**
   * Loads the actor user from the provided queue item.
   *
   * @param array $data
   *   The queue item array with string or integer value at the "user" key.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user account to use as actor, or NULL if it no longer exists.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getActorFromItem(array $data): ?UserInterface {
    $actor = $this->entityTypeManager->getStorage('user')->load($data['user']);
    if (!$actor instanceof UserInterface) {
      $this->logger->error(
        "Cannot archive entities of item with ID '%groupId': actor account with ID '%actor' is missing.",
        [
          '%groupId' => $data['group'],
          '%actor' => $data['user'],
        ],
      );
      return NULL;
    }
    return $actor;
  }

}
