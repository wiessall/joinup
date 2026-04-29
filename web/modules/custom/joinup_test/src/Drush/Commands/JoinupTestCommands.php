<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the Joinup test module.
 */
class JoinupTestCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * UUIDs of nodes created during site install that should be preserved.
   *
   * @see \Drupal\joinup_test\DefaultContentHelper
   */
  private const array BASE_NODE_UUIDS = [
    JoinupCollectionHelper::JOINUP_CONTACT_UUID,
    JoinupCollectionHelper::JOINUP_OWNER_UUID,
    JoinupCollectionHelper::JOINUP_COLLECTION_UUID,
    AssessmentInterface::GROUP_UUID,
    AssessmentInterface::SOLUTION_COLLECTOR,
  ];

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Deletes orphan nodes left behind by PHPUnit ExistingSite tests.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  #[CLI\Command(name: 'joinup-test:cleanup-nodes')]
  #[CLI\Usage(name: 'drush joinup-test:cleanup-nodes', description: 'Delete all orphan nodes not part of the base install.')]
  public function cleanupNodes(): void {
    $storage = $this->entityTypeManager->getStorage('node');

    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('uuid', self::BASE_NODE_UUIDS, 'NOT IN')
      ->execute();

    if (empty($nids)) {
      $this->logger()->success(dt('No orphan nodes found.'));
      return;
    }

    $storage->delete($storage->loadMultiple($nids));
    $this->logger()->success(dt('Deleted @count orphan node(s).', [
      '@count' => count($nids),
    ]));
  }

}
