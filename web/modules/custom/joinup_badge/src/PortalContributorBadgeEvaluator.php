<?php

declare(strict_types=1);

namespace Drupal\joinup_badge;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Awards and gates the 'portal_contributor' badge.
 */
readonly class PortalContributorBadgeEvaluator {

  /**
   * The badge flag identifier.
   */
  public const string FLAG_ID = 'portal_contributor';

  /**
   * The queue ID consumed by the badge eligibility worker.
   */
  public const string QUEUE_ID = 'joinup_badge_portal_contributor_check';

  /**
   * Node bundles that count as eligible publications.
   *
   * Custom pages, distributions, releases, owners, contact information,
   * licences, glossary entries and OSS contractor / contact / solution
   * variants are intentionally excluded.
   */
  public const array CONTENT_BUNDLES = [
    'solution',
    'collection',
    'event',
    'news',
    'discussion',
    'document',
    'assessment_report',
  ];

  /**
   * The minimum number of publications required to qualify.
   */
  protected const int THRESHOLD = 5;

  /**
   * The trailing period over which publications are counted.
   */
  protected const string PERIOD = '-2 months';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected Connection $database,
    protected QueueFactory $queueFactory,
    #[Autowire(service: 'flag')]
    protected FlagServiceInterface $flagService,
    protected BadgeService $badgeService,
  ) {}

  /**
   * Awards the badge to the user if they meet the threshold.
   */
  public function evaluate(UserInterface $user): void {
    if ($user->isAnonymous()) {
      return;
    }
    $flag = $this->flagService->getFlagById(static::FLAG_ID);
    // Skip the count query if the badge is already held — it's a permanent
    // award, so re-evaluating cannot change the outcome.
    if (!$flag || $flag->isFlagged($user)) {
      return;
    }
    if ($this->countContributions($user) >= static::THRESHOLD) {
      $this->badgeService->award(static::FLAG_ID, $user);
    }
  }

  /**
   * Enqueues distinct authors of content published since a given timestamp.
   *
   * @param int $sinceTimestamp
   *   Unix timestamp; only content with `created >= $sinceTimestamp` is
   *   considered. Pass 0 to scan everything (e.g. in tests).
   *
   * @return int
   *   The number of unique uids enqueued.
   */
  public function enqueueRecentAuthors(int $sinceTimestamp): int {
    $nodeUids = $this->database->select('node_field_data', 'n')
      ->fields('n', ['uid'])
      ->condition('n.status', 1)
      ->condition('n.type', static::CONTENT_BUNDLES, 'IN')
      ->condition('n.created', $sinceTimestamp, '>=')
      ->distinct()
      ->execute()
      ->fetchCol();

    $commentUids = $this->database->select('comment_field_data', 'c')
      ->fields('c', ['uid'])
      ->condition('c.status', 1)
      ->condition('c.created', $sinceTimestamp, '>=')
      ->distinct()
      ->execute()
      ->fetchCol();

    $uids = array_filter(
      array_unique(array_map('intval', array_merge($nodeUids, $commentUids))),
      fn(int $uid): bool => $uid > 0,
    );

    if (!$uids) {
      return 0;
    }

    $queue = $this->queueFactory->get(static::QUEUE_ID);
    foreach ($uids as $uid) {
      $queue->createItem(['uid' => $uid]);
    }

    return count($uids);
  }

  /**
   * Counts published nodes and comments authored by the user in the period.
   */
  protected function countContributions(UserInterface $user): int {
    $cutoff = (new \DateTimeImmutable())->modify(static::PERIOD)->getTimestamp();
    $uid = (int) $user->id();

    $nodeCount = (int) $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->condition('type', static::CONTENT_BUNDLES, 'IN')
      ->condition('created', $cutoff, '>=')
      ->count()
      ->execute();

    if ($nodeCount >= static::THRESHOLD) {
      return $nodeCount;
    }

    $commentCount = (int) $this->entityTypeManager->getStorage('comment')->getQuery()
      ->accessCheck(FALSE)
      ->condition('uid', $uid)
      ->condition('status', 1)
      ->condition('created', $cutoff, '>=')
      ->count()
      ->execute();

    return $nodeCount + $commentCount;
  }

}
