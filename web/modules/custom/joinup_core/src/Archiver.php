<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\UpdateException;
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

  const METHODS_ALLOWED = [
    'doArchiveGroupsWithFewMembers',
    'doArchiveCollectionsWithoutSolutions' ,
    'doArchivePotentiallyOutdatedContent',
    'doArchiveSolutionsWithoutReleaseOrDistro',
    'doArchiveGroupsWithoutContent',
  ];

  /**
   * The workflow helper service, if available.
   *
   * @var \Drupal\joinup_workflow\WorkflowHelperInterface|null
   */
  protected ?WorkflowHelperInterface $workflowHelper;

  private const QUEUE_STATS_COLLECTION = 'joinup_core_queue_stats';

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.joinup_core')]
    protected readonly LoggerChannelInterface $logger,
    protected readonly AccountSwitcherInterface $accountSwitcher,
    protected readonly Connection $database,
    protected KeyValueFactoryInterface $keyValue,
  ) {}

  /**
   * Gets stats store.
   */
  private function getQueueStatsStore(): KeyValueStoreInterface {
    return $this->keyValue->get(self::QUEUE_STATS_COLLECTION);
  }

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
   * Archives groups from CSV which have less than 10 members.
   */
  protected function doArchiveGroupsWithFewMembers(int $groupId): void {
    $this->incrementStats(__FUNCTION__, 'total');
    $group = $this->entityTypeManager->getStorage('node')->load($groupId);
    if (!$group instanceof GroupInterface) {
      return;
    }

    if (!$group->isPublished()) {
      return;
    }

    if ($group->getActiveMemberCount() >= 10) {
      return;
    }

    if ($this->archiveContent($group)) {
      $this->incrementStats(__FUNCTION__, 'archived');
    }
  }

  /**
   * Archives collections from CSV which have no solutions.
   */
  protected function doArchiveCollectionsWithoutSolutions(int $groupId): void {
    $this->incrementStats(__FUNCTION__, 'total');
    $collection = $this->entityTypeManager->getStorage('node')->load($groupId);
    if (!$collection instanceof CollectionInterface) {
      return;
    }

    if (!$collection->isPublished()) {
      return;
    }

    if ($collection->getSolutions()) {
      return;
    }

    if ($this->archiveContent($collection)) {
      $this->incrementStats(__FUNCTION__, 'archived');
    }
  }

  /**
   * Archives content from CSV which wasn't updated in the last 3 years.
   */
  protected function doArchivePotentiallyOutdatedContent(int $nid): void {
    $this->incrementStats(__FUNCTION__, 'total');
    $storage = $this->entityTypeManager->getStorage('node');
    assert($storage instanceof RevisionableStorageInterface);
    $content = $storage->load($nid);
    if (!$content instanceof NodeInterface) {
      return;
    }
    if (!$content->isPublished()) {
      return;
    }
    $latestRevision = $storage->loadRevision(
      $storage->getLatestRevisionId($nid)
    );
    if ($latestRevision->getChangedTimeAcrossTranslations() >= strtotime('-3 years')) {
      return;
    }

    if ($this->archiveContent($content)) {
      $this->incrementStats(__FUNCTION__, 'archived');
    }
  }

  /**
   * Archives solutions from CSV which have no releases or distributions.
   */
  protected function doArchiveSolutionsWithoutReleaseOrDistro(int $solutionId): void {
    $this->incrementStats(__FUNCTION__, 'total');
    $solution = $this->entityTypeManager->getStorage('node')->load($solutionId);
    if (!$solution instanceof SolutionInterface) {
      return;
    }
    if (!$solution->isPublished()) {
      return;
    }
    if ($solution->getDistributionIds() || $solution->getReleaseIds()) {
      return;
    }
    if ($this->archiveContent($solution)) {
      $this->incrementStats(__FUNCTION__, 'archived');
    }
  }

  /**
   * Archives groups from CSV which have no group content.
   */
  protected function doArchiveGroupsWithoutContent(int $groupId): void {
    $this->incrementStats(__FUNCTION__, 'total');
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $group = $nodeStorage->load($groupId);
    if (!$group instanceof GroupInterface) {
      return;
    }
    if (!$group->isPublished()) {
      return;
    }

    $bundles = ['custom_page', 'discussion', 'document', 'event', 'news'];
    $groupContentIdsRaw = $group->getGroupContentIds();
    $groupContentNodeIds = array_intersect_key(
      $groupContentIdsRaw['node'] ?? [],
      array_combine($bundles, $bundles)
    );
    $groupContentIds = array_merge(...array_values($groupContentNodeIds));
    $notArchivedGroupContentIds = array_filter(
      $groupContentIds,
      function ($groupContentId) use ($nodeStorage): bool {
        $node = $nodeStorage->load($groupContentId);
        return !$node instanceof ArchivableEntityInterface || !$node->isArchived();
      },
    );
    if (array_filter($notArchivedGroupContentIds)) {
      return;
    }
    if ($this->archiveContent($group)) {
      $this->incrementStats(__FUNCTION__, 'archived');
    }
  }

  /**
   * Tries to archive a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to be archived.
   */
  public function archiveContent(ContentEntityInterface $entity): bool {
    if (
      !$entity instanceof EntityPublishedInterface ||
      !$entity instanceof ArchivableEntityInterface
    ) {
      $this->logger->warning('Cannot archive content with ID @content-id: content does not implement ArchivableEntityInterface.', [
        '@content-id' => $entity->id(),
      ]);
      return FALSE;
    }

    // Current user cannot change the state to "archived".
    $allowedTransitions = $entity->getWorkflow()
      ->getAllowedTransitions($entity->getWorkflowState(), $entity);
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
        'the Portal',
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
   * Returns the integer values of the given CSV's first column.
   *
   * @param string $csvPath
   *   Path to the CSV file.
   *
   * @return int[]
   *   The integer values of the given CSV's first column.
   */
  public static function getIdsFromCsvColumn(string $csvPath): array {
    $ids = [];
    $csvHandle = fopen($csvPath, 'r');
    if ($csvHandle) {
      while (($data = fgetcsv($csvHandle, 0, ',', '"', '\\')) !== FALSE) {
        $nidInt = intval($data[0]);
        if (((string) $nidInt) !== $data[0]) {
          continue;
        }
        $ids[] = $nidInt;
      }
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
    if ($idHash !== 'qgVoA498SIMOFFVhb-25_eXOYYlgYvRpl7z1LlieR4M') {
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

  /**
   * Switches account.
   *
   * @param int $groupId
   *   Processed item nid.
   * @param string $method
   *   Source method.
   */
  public function callMethodSafely(int $groupId, string $method): void {
    if (!in_array($method, self::METHODS_ALLOWED, TRUE)) {
      throw new \Exception(sprintf(
        'Calling method %s is not allowed.',
        $method,
      ));
    }
    if (!is_callable([$this, $method])) {
      throw new \Exception(sprintf(
        'Method %s does not exist on class %s or it is not callable.',
        $method,
        __CLASS__,
      ));
    }

    $this->switchToServiceAccount();
    try {
      $args = [$groupId];
      call_user_func_array([$this, $method], $args);
    }
    finally {
      $this->switchBack();
    }
  }

  /**
   * Returns all gathered stats.
   *
   * @return array
   *   The stats.
   */
  public function getStats(): array {
    $store = $this->getQueueStatsStore();
    $allStats = [];
    foreach (self::METHODS_ALLOWED as $method) {
      $allStats[$method] = $store->get($method, [
        'total' => 0,
        'archived' => 0,
      ]);
    }
    return $allStats;
  }

  /**
   * Logs all gathered stats.
   */
  public function logStats(): void {
    foreach ($this->getStats() as $method => $stats) {
      $this->logger->info('Method @method: @total total, @archived archived', [
        '@method' => $method,
        '@total' => $stats['total'],
        '@archived' => $stats['archived'],
      ]);
    }
  }

  /**
   * Increments total and archived items count per method.
   *
   * @param string $method
   *   Source function, statd identifier.
   * @param string $stat
   *   Total and archived items count.
   */
  private function incrementStats(string $method, string $stat): void {
    $store = $this->getQueueStatsStore();
    $stats = $store->get($method, [
      'total' => 0,
      'archived' => 0,
    ]);

    $stats[$stat]++;
    $store->set($method, $stats);
  }

}
