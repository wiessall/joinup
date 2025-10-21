<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowInterface;
use Drupal\state_machine\Plugin\Workflow\WorkflowTransition;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Contains helper methods to retrieve workflow related data from entities.
 */
class WorkflowHelper implements WorkflowHelperInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly AccountProxyInterface $currentUser,
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly EntityFieldManagerInterface $entityFieldManager,
    #[Autowire(service: 'og.membership_manager')]
    protected readonly MembershipManagerInterface $membershipManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly TimeInterface $time,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAvailableTargetStates(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): array {
    /** @var \Drupal\joinup_workflow\EntityWorkflowStateInterface $entity */
    $allowedTransitions = $this->getAvailableTransitions($entity, $account);

    return array_map(function (WorkflowTransition $transition) {
      return (string) $transition->getToState()->getId();
    }, $allowedTransitions);
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableTransitions(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): array {
    /** @var \Drupal\joinup_workflow\EntityWorkflowStateInterface $entity */
    // Set the current user so that states available are retrieved for the
    // specific account.
    // The proper solution would be to pass the account to the state_machine
    // field method, to avoid these account switch trickeries.
    // @todo change this once the upstream issue is fixed.
    // @see https://www.drupal.org/node/2776969
    $accountSwitched = FALSE;
    if ($account !== NULL && $account->id() !== $this->currentUser->id()) {
      $this->accountSwitcher->switchTo($account);
      $accountSwitched = TRUE;
    }

    $transitions = $entity->getWorkflowStateField()->getTransitions();

    if ($accountSwitched) {
      $this->accountSwitcher->switchBack();
    }

    return $transitions;
  }

  /**
   * {@inheritdoc}
   */
  public function checkTransitionAccess(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): AccessResultInterface {
    $transitions = $this->getAvailableTransitions($entity, $account);

    $access = !empty($transitions)
      ? AccessResult::allowed()
      : AccessResult::forbidden('No available transitions for the entity.');

    return $access
      ->addCacheContexts(['user.permissions', 'user'])
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityStateFieldDefinition(string $entityTypeId, string $bundle): ?FieldDefinitionInterface {
    if ($fieldDefinitions = $this->getEntityStateFieldDefinitions($entityTypeId, $bundle)) {
      return reset($fieldDefinitions);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityStateFieldDefinitions(string $entityTypeId, string $bundle): array {
    return array_filter($this->entityFieldManager->getFieldDefinitions($entityTypeId, $bundle), function (FieldDefinitionInterface $field_definition) {
      return $field_definition->getType() == 'state';
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableTransitionsLabels(FieldableEntityInterface $entity, ?AccountInterface $account = NULL): array {
    return array_map(function (WorkflowTransition $transition) {
      return (string) $transition->getLabel();
    }, $this->getAvailableTransitions($entity, $account));
  }

  /**
   * {@inheritdoc}
   */
  public function isWorkflowStatePublished(string $stateId, WorkflowInterface $workflow): bool {
    // We rely on being able to inspect the plugin definition. Throw an error if
    // this is not the case.
    if (!$workflow instanceof PluginInspectionInterface) {
      $label = $workflow->getLabel();
      throw new \InvalidArgumentException("The '$label' workflow is not plugin based.");
    }

    // Retrieve the raw plugin definition, as all additional plugin settings
    // are stored there.
    $rawWorkflowDefinition = $workflow->getPluginDefinition();
    return !empty($rawWorkflowDefinition['states'][$stateId]['published']);
  }

  /**
   * Helper method to retrieve the parent of the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The group content entity.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The group the entity belongs to, or NULL when no group is found.
   */
  protected function getEntityParent(EntityInterface $entity): ?NodeInterface {
    $groups = $this->membershipManager->getGroups($entity);
    if (empty($groups['node'])) {
      return NULL;
    }

    return reset($groups['node']);
  }

  /**
   * {@inheritdoc}
   */
  public function archiveEntity(EntityWorkflowStateInterface $entity, null|string|Stringable|TranslatableMarkup $revisionLogMessage = NULL): EntityWorkflowStateInterface {
    if (
      !$entity instanceof ArchivableEntityInterface ||
      $entity->isArchived() ||
      !$entity->isTargetWorkflowStateAllowed('archived')
    ) {
      throw new \Exception(sprintf(
        "The %s entity with id '%s' cannot be archived.",
        $entity->getEntityType()->getLabel(),
        $entity->id(),
      ));
    }

    if ($entity instanceof RevisionableInterface && $entity->getEntityType()->isRevisionable()) {
      $entity->setNewRevision();
      $entity->isDefaultRevision(TRUE);
      if ($entity instanceof RevisionLogInterface) {
        $revisionLogMessage = $revisionLogMessage ?? $this->t('Archived by user @user', [
          '@user' => $this->currentUser->getAccountName(),
        ]);
        $entity->setRevisionLogMessage((string) $revisionLogMessage)
          ->setRevisionUserId($this->currentUser->id())
          ->setRevisionCreationTime($this->time->getRequestTime());
      }
    }

    $entity->setWorkflowState('archived');
    $entity->save();
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function unarchiveEntity(
    EntityInterface $entity,
    string|Stringable|TranslatableMarkup|null $revisionLogMessage = NULL,
  ): EntityInterface {
    if (
      !$entity instanceof ArchivableEntityInterface ||
      !$entity->isArchived()
    ) {
      throw new \Exception(sprintf(
        "The %s entity with id '%s' cannot be unarchived.",
        $entity->getEntityType()->getLabel(),
        $entity->id(),
      ));
    }

    // Revert to the previous revision of the entity.
    $entityType = $entity->getEntityType();
    if (!$entity instanceof RevisionableInterface || !$entityType->isRevisionable()) {
      $entity->setWorkflowState('published')->save();
      return $entity;
    }
    $entityStorage = $this->entityTypeManager->getStorage($entityType->id());
    assert($entityStorage instanceof RevisionableStorageInterface);

    // Load the previous published revision's IDs.
    $revisionIdsQuery = $entityStorage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($entityType->getKey('id'), $entity->id())
      ->condition($entity->getWorkflowStateFieldName(), 'published')
      ->sort($entityType->getKey('revision'), 'DESC');
    if ($defaultRevisionKey = $entityType->getRevisionMetadataKey('revision_default')) {
      $revisionIdsQuery->condition($defaultRevisionKey, TRUE);
    }

    $revisionIds = array_keys($revisionIdsQuery->execute());
    $revisionId = reset($revisionIds);
    if (!$revisionId) {
      throw new \Exception(sprintf(
        "The %s entity with id '%s' cannot be unarchived: cannot find the previous revision it was archived from.",
        $entity->getEntityType()->getLabel(),
        $entity->id(),
      ));
    }

    // Load the previous revision.
    $previousRevision = $entityStorage->loadRevision(reset($revisionIds));
    \assert($previousRevision instanceof RevisionableInterface);
    $previousRevision->setNewRevision();
    $previousRevision->isDefaultRevision(TRUE);
    if ($previousRevision instanceof RevisionLogInterface) {
      $revisionLogMessage = $revisionLogMessage ?? $this->t('Unarchived by @user', [
        '@user' => $this->currentUser->getAccountName(),
      ]);
      if ($revisionLogMessage) {
        $previousRevision->setRevisionLogMessage((string) $revisionLogMessage);
      }
      $previousRevision->setRevisionCreationTime($this->time->getRequestTime());
      $previousRevision->setRevisionUserId($this->currentUser->id());
    }
    $previousRevision->skip_notification = TRUE;
    $previousRevision->save();

    return $previousRevision;
  }

  /**
   * {@inheritdoc}
   */
  public function accountCanNotUnarchive(EntityInterface $entity, AccountInterface $account): bool {
    return $entity->getWorkflowState() === 'archived' && !$account->hasPermission('unarchived community content');
  }

}
