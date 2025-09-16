<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\UpdateException;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Archives content.
 */
class Archiver {

  use StringTranslationTrait;

  /**
   * The workflow helper service, if available.
   *
   * @var \Drupal\joinup_workflow\WorkflowHelperInterface|null
   */
  protected ?WorkflowHelperInterface $workflowHelper;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.joinup_core')]
    protected readonly LoggerChannelInterface $logger,
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly Connection $database,
  ) {}

  /**
   * Sets the workflow helper service.
   *
   * @param \Drupal\joinup_workflow\WorkflowHelperInterface|null $workflowHelper
   *   The workflow helper service.
   */
  public function setWorkflowHelper(?WorkflowHelperInterface $workflowHelper): void {
    $this->workflowHelper = $workflowHelper;
  }

  /**
   * Archives groups from CSV with the service account.
   */
  public function archiveGroupsWithFewMembers(array &$sandbox): void {
    $this->switchToServiceAccount();
    try {
      $this->doArchiveGroupsWithFewMembers($sandbox);
    }
    finally {
      $this->switchBack();
    }
  }

  /**
   * Archives groups from CSV which have less than 10 members.
   */
  protected function doArchiveGroupsWithFewMembers(array &$sandbox): void {
    if (!isset($sandbox['groupIds'])) {
      $sandbox['groupIds'] = static::getIdsFromCsvColumn(dirname(__DIR__) . '/deploy/archival-csv/WITH-NID Collections & Solutions with less than 10 members.csv');
      $sandbox['archived'] = 0;
      $sandbox['processed'] = 0;
      $sandbox['total'] = count($sandbox['groupIds']);
    }

    $groupIds = array_splice($sandbox['groupIds'], 0, 100);
    foreach ($groupIds as $groupId) {
      $group = $this->entityTypeManager->getStorage('node')->load($groupId);
      if (!$group instanceof GroupInterface) {
        continue;
      }

      if (!$group->isPublished()) {
        continue;
      }

      if ($group->getActiveMemberCount() >= 10) {
        continue;
      }

      $sandbox['archived'] += (int) $this->archiveContent($group);
    }

    $sandbox['processed'] += count($groupIds);
    $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];
  }

  /**
   * Archives collections from CSV with the service account.
   */
  public function archiveCollectionsWithoutSolutions(array &$sandbox): void {
    $this->switchToServiceAccount();
    try {
      $this->doArchiveCollectionsWithoutSolutions($sandbox);
    }
    finally {
      $this->switchBack();
    }
  }

  /**
   * Archives collections from CSV which have no solutions.
   */
  protected function doArchiveCollectionsWithoutSolutions(array &$sandbox): void {
    if (!isset($sandbox['collectionIds'])) {
      $sandbox['collectionIds'] = static::getIdsFromCsvColumn(dirname(__DIR__) . '/deploy/archival-csv/WITH-NID Collections with no solutions.csv');
      $sandbox['archived'] = 0;
      $sandbox['processed'] = 0;
      $sandbox['total'] = count($sandbox['collectionIds']);
    }

    $collectionIds = array_splice($sandbox['collectionIds'], 0, 50);
    foreach ($collectionIds as $collectionId) {
      $collection = $this->entityTypeManager->getStorage('node')->load($collectionId);
      if (!$collection instanceof CollectionInterface) {
        continue;
      }

      if (!$collection->isPublished()) {
        continue;
      }

      if ($collection->getSolutions()) {
        continue;
      }

      $sandbox['archived'] += (int) $this->archiveContent($collection);
    }

    $sandbox['processed'] += count($collectionIds);
    $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];
  }

  /**
   * Archives groups from CSV with the service account.
   */
  public function archivePotentiallyOutdatedContent(array &$sandbox): void {
    $this->switchToServiceAccount();
    try {
      $this->doArchivePotentiallyOutdatedContent($sandbox);
    }
    finally {
      $this->switchBack();
    }
  }

  /**
   * Archives content from CSV which wasn't updated in the last 3 years.
   */
  protected function doArchivePotentiallyOutdatedContent(array &$sandbox): void {
    if (!isset($sandbox['contentIds'])) {
      $sandbox['contentIds'] = static::getIdsFromCsvColumn(dirname(__DIR__) . '/deploy/archival-csv/WITH-NID Last update - 3 years ago.csv');
      $sandbox['archived'] = 0;
      $sandbox['processed'] = 0;
      $sandbox['total'] = count($sandbox['contentIds']);
      $sandbox['threeYearsAgo'] = strtotime('-3 years');
    }

    $contentIds = array_splice($sandbox['contentIds'], 0, 50);
    foreach ($contentIds as $nid) {
      $storage = $this->entityTypeManager->getStorage('node');
      assert($storage instanceof RevisionableStorageInterface);
      $content = $storage->load($nid);
      if (!$content instanceof NodeInterface) {
        continue;
      }
      if (!$content->isPublished()) {
        continue;
      }
      $latestRevision = $storage->loadRevision(
        $storage->getLatestRevisionId($nid)
      );
      if ($latestRevision->getChangedTimeAcrossTranslations() >= $sandbox['threeYearsAgo']) {
        continue;
      }

      $sandbox['archived'] += (int) $this->archiveContent($content);
    }

    $sandbox['processed'] += count($contentIds);
    $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];
  }

  /**
   * Archives groups from CSV with the service account.
   */
  public function archiveSolutionsWithoutReleaseOrDistro(array &$sandbox): void {
    $this->switchToServiceAccount();
    try {
      $this->doArchiveSolutionsWithoutReleaseOrDistro($sandbox);
    }
    finally {
      $this->switchBack();
    }
  }

  /**
   * Archives solutions from CSV which have no releases or distributions.
   */
  protected function doArchiveSolutionsWithoutReleaseOrDistro(array &$sandbox): void {
    if (!isset($sandbox['solutionIds'])) {
      $sandbox['solutionIds'] = static::getIdsFromCsvColumn(dirname(__DIR__) . '/deploy/archival-csv/WITH-NID No releases or distributions.csv');
      $sandbox['archived'] = 0;
      $sandbox['processed'] = 0;
      $sandbox['total'] = count($sandbox['solutionIds']);
    }

    $solutionIds = array_splice($sandbox['solutionIds'], 0, 100);
    foreach ($solutionIds as $solutionId) {
      $solution = $this->entityTypeManager->getStorage('node')->load($solutionId);
      if (!$solution instanceof SolutionInterface) {
        continue;
      }
      if (!$solution->isPublished()) {
        continue;
      }
      if ($solution->getDistributionIds() || $solution->getReleaseIds()) {
        continue;
      }
      $sandbox['archived'] += (int) $this->archiveContent($solution);
    }

    $sandbox['processed'] += count($solutionIds);
    $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];
  }

  /**
   * Archives groups from CSV with the service account.
   */
  public function archiveGroupsWithoutContent(array &$sandbox): void {
    $this->switchToServiceAccount();
    try {
      $this->doArchiveGroupsWithoutContent($sandbox);
    }
    finally {
      $this->switchBack();
    }
  }

  /**
   * Archives groups from CSV which have no group content.
   */
  protected function doArchiveGroupsWithoutContent(array &$sandbox): void {
    if (!isset($sandbox['groupIds'])) {
      $sandbox['groupIds'] = static::getIdsFromCsvColumn(dirname(__DIR__) . '/deploy/archival-csv/WITH-NID Solutions & Collections with no content items.csv');
      $sandbox['archived'] = 0;
      $sandbox['processed'] = 0;
      $sandbox['total'] = count($sandbox['groupIds']);
    }

    $groupIds = array_splice($sandbox['groupIds'], 0, 100);
    foreach ($groupIds as $groupId) {
      $group = $this->entityTypeManager->getStorage('node')->load($groupId);
      if (!$group instanceof GroupInterface) {
        continue;
      }
      if (!$group->isPublished()) {
        continue;
      }

      $groupContentIds = $group->getGroupContentIds();
      $activeMembers = $group->getActiveMemberCount();
      if (array_filter($groupContentIds) && $activeMembers) {
        continue;
      }

      $sandbox['archived'] += (int) $this->archiveContent($group);
    }

    $sandbox['processed'] += count($groupIds);
    $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];
  }

  /**
   * Tries to archive a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to be archived.
   */
  protected function archiveContent(ContentEntityInterface $entity): bool {
    if (
      !$entity instanceof EntityPublishedInterface ||
      !$entity instanceof ArchivableEntityInterface
    ) {
      $this->logger->warning('Cannot archive content with ID @content-id: content does not implement ArchivableEntityInterface.', [
        '@content-id' => $entity->id(),
      ]);
      return FALSE;
    }

    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    // Load the published version of the entity if the entity is published.
    if (!$entity->isPublished()) {
      $entity = $storage->load($entity->id()) ?? $entity;
    }

    // Current user cannot change the state to "archived".
    $allowedTransitions = $entity->getWorkflow()->getAllowedTransitions($entity->getWorkflowState(), $entity);
    if (!array_key_exists('archive', $allowedTransitions)) {
      $this->logger->warning('Cannot archive content with ID @content-id: content does not have any available transition with "archived" target state.', [
        '@content-id' => $entity->id(),
      ]);
      return FALSE;
    }

    $targetState = $allowedTransitions['archive']->getToState()->getId();
    // No need to archive unpublished or already archived entities.
    if (
      $entity->getWorkflowState() === $targetState ||
      !$entity->isPublished()
    ) {
      // Continue silently.
      return FALSE;
    }

    $entity->skip_notification = TRUE;
    try {
      $revisionLogMessage = $this->workflowStateChangeRevisionLogMessage(
        $entity,
        'archived',
        'Joinup',
      );
      $this->workflowHelper->archiveEntity($entity, $revisionLogMessage);
    }
    catch (\Exception $exception) {
      $this->logger->warning($exception->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Returns the ID list of the exclusive nodes.
   *
   * @return int[]
   *   IDs of those nodes which shouldn't be archived.
   */
  protected static function getExclusions(): array {
    static $exclusions;

    if (!isset($exclusions)) {
      $exclusions = static::getIdsFromCsvColumn(dirname(__DIR__) . '/deploy/archival-csv/EXCLUSIONS.csv', FALSE);
    }

    return $exclusions;
  }

  /**
   * Returns the integer values of the given CSV's first column.
   *
   * @param string $csvPath
   *   Path to the CSV file.
   * @param bool $filter
   *   Whether the exclusion should be applied or not.
   *
   * @return int[]
   *   The integer values of the given CSV's first column.
   */
  protected static function getIdsFromCsvColumn(string $csvPath, bool $filter = TRUE): array {
    $ids = [];
    $csvHandle = fopen($csvPath, 'r');
    if ($csvHandle) {
      while (($data = fgetcsv($csvHandle)) !== FALSE) {
        $nidInt = intval($data[0]);
        if (((string) $nidInt) !== $data[0]) {
          continue;
        }
        $ids[] = $nidInt;
      }
    }

    if ($filter) {
      $ids = array_diff($ids, static::getExclusions());
    }

    return $ids;
  }

  /**
   * Switches to the service account.
   */
  protected function switchToServiceAccount(): void {
    $this->accountSwitcher->switchTo($this->getServiceAccount());
  }

  /**
   * Returns the service account.
   *
   * @return \Drupal\user\UserInterface
   *   The service account.
   */
  protected function getServiceAccount(): UserInterface {
    $uidQuery = $this->database->select('users_field_data', 'u')
      ->fields('u', ['uid']);
    $uidQuery->innerJoin('user__roles', 'ur', 'ur.entity_id = u.uid');
    $uidQuery
      ->condition('ur.roles_target_id', 'moderator')
      ->orderBy('u.created')
      ->range(9, 1);
    $uid = $uidQuery->execute()->fetchField();
    if (!$uid) {
      throw new UpdateException('Service account not found');
    }
    $serviceAccount = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$serviceAccount instanceof UserInterface) {
      throw new UpdateException('Service account is not loadable');
    }
    $idHash = Crypt::hashBase64((string) $serviceAccount->id() . $serviceAccount->uuid() . (string) $serviceAccount->getCreatedTime());
    if ($idHash !== 'NKIl5O1OD62pfz97xaZYrin5pSKes9PmzFwcRgVyf0M') {
      throw new UpdateException('Service account is invalid');
    }

    return $serviceAccount;
  }

  /**
   * Switch back current user account to the previously active one.
   */
  protected function switchBack(): void {
    $this->accountSwitcher->switchBack();
  }

  /**
   * Constructs the revision log message for the newly saved entity version.
   *
   * Copied from WorkflowStateChangeMessageTrait to make this class easier to
   * remove.
   *
   * @param \Drupal\joinup_workflow\ArchivableEntityInterface $entity
   *   The entity.
   * @param string $newState
   *   The new workflow state ID of the entity.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $performer
   *   The performer (a queue worker, action plugin) to be referred in the
   *   log message. The current class is used if this parameter is omitted.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The revision log message for the newly saved entity version, or NULL if
   *   the provided entity is not revisionable.
   */
  protected function workflowStateChangeRevisionLogMessage(
    ArchivableEntityInterface $entity,
    string $newState,
    string|TranslatableMarkup|null $performer = NULL,
  ): ?TranslatableMarkup {
    if (
      !$entity instanceof RevisionableInterface ||
      !$entity->getEntityType()->isRevisionable()
    ) {
      return NULL;
    }

    return $this->t(
      "Updated state to '@state-new' by @performer, from revision ID '@revision-id-original' with state '@state-original'.",
      [
        '@performer' => $performer ?? __CLASS__,
        '@revision-id-original' => $entity->getRevisionId(),
        '@state-original' => $entity->getWorkflowState(),
        '@state-new' => $newState,
      ]
    );
  }

}
