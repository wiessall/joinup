<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Update;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\joinup_core\Update\UpdateProcessInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\joinup_workflow\WorkflowStateChangeMessageTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Archives releases that have not been published in more than 2 years.
 */
class ArchiveOldReleasesProcess implements UpdateProcessInterface, ContainerInjectionInterface {

  use WorkflowStateChangeMessageTrait;

  private const int BATCH_SIZE = 10;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly WorkflowHelperInterface $workflowHelper,
    private readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get(WorkflowHelperInterface::class),
      $container->get('logger.factory')->get('joinup_release'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process(array &$sandbox): void {
    if (!isset($sandbox['ids'])) {
      $sandbox['ids'] = $this->loadCandidateReleaseIds();
      $sandbox['processed'] = 0;
      $sandbox['total'] = count($sandbox['ids']);
    }

    $ids = array_splice($sandbox['ids'], 0, self::BATCH_SIZE);
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    foreach ($ids as $nid) {
      $release = $nodeStorage->load($nid);
      assert($release instanceof ReleaseInterface);

      $this->archiveRelease($release);
    }

    $sandbox['processed'] += count($ids);
    $sandbox['#finished'] = $sandbox['total'] ? $sandbox['processed'] / $sandbox['total'] : 1;
  }

  /**
   * Returns IDs of published releases older than 2 years.
   *
   * @return int[]
   *   The release node IDs.
   */
  private function loadCandidateReleaseIds(): array {
    $twoYearsAgo = strtotime('-2 years');

    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->condition('type', 'release')
      ->condition('status', 1)
      ->condition('field_state', 'published')
      ->condition('published_at', $twoYearsAgo, '<')
      ->accessCheck(FALSE)
      ->execute();

    return array_values($ids);
  }

  /**
   * Archives a release and its distributions.
   *
   * @param \Drupal\joinup_release\Entity\ReleaseInterface $release
   *   The release to archive.
   */
  private function archiveRelease(ReleaseInterface $release): void {
    foreach ($release->getDistributions() as $distribution) {
      $this->doArchive($distribution);
    }

    $this->doArchive($release);
  }

  /**
   * Performs the archive workflow transition on an entity.
   *
   * @param \Drupal\joinup_workflow\ArchivableEntityInterface $entity
   *   The entity to archive.
   */
  private function doArchive(ArchivableEntityInterface $entity): void {
    $entity->skip_notification = TRUE;
    try {
      $revisionLogMessage = $this->workflowStateChangeRevisionLogMessage(
        $entity,
        'archived',
        'automated archiving',
      );
      $this->workflowHelper->archiveEntity($entity, $revisionLogMessage);
    }
    catch (\Exception $e) {
      $this->logger->warning(
        'Could not archive @type @id: @message',
        [
          '@type' => $entity->getEntityTypeId(),
          '@id' => $entity->id(),
          '@message' => $e->getMessage(),
        ],
      );
    }
  }

}
