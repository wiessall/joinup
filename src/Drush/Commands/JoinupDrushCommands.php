<?php

declare(strict_types=1);

namespace Joinup\Drush\Commands;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\search_api\Task\TaskManagerInterface;
use Drupal\simple_oauth\Service\KeyGeneratorService;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Specific commands to run on Interoperable Europe Portal code deployment.
 */
class JoinupDrushCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'search_api.task_manager')]
    protected readonly TaskManagerInterface $taskManager,
    protected readonly ModuleInstallerInterface $moduleInstaller,
    protected readonly FileSystemInterface $fileSystem,
    #[Autowire(service: 'simple_oauth.key.generator')]
    protected readonly KeyGeneratorService $keyGenerator,
  ) {
    parent::__construct();
  }

  /**
   * Rebuilds node access if case.
   */
  #[CLI\Command(name: 'joinup:node-access-rebuild')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function nodeAccessRebuild(): void {
    if (node_access_needs_rebuild()) {
      $this->logger()->notice('Rebuilding node access... (patience, it takes a while)');
      node_access_rebuild();
      return;
    }
    $this->logger()->notice("Node access doesn't need rebuild.");
  }

  /**
   * Execute all Search API pending tasks.
   */
  #[CLI\Command(name: 'joinup:search-api-tasks')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function executeSearchApiPendingTasks(): void {
    /** @var \Drupal\search_api\Task\TaskManagerInterface $sapiTaskManager */
    $sapiTaskManager = $this->taskManager;
    // Execute all Search API pending tasks.
    $pending_tasks = (bool) $sapiTaskManager->getTasksCount();
    if ($pending_tasks) {
      $sapiTaskManager->setTasksBatch();
      if (batch_get()) {
        drush_backend_batch_process();
      }
    }
  }

  /**
   * Runs specific acceptance tasks.
   */
  #[CLI\Command(name: 'joinup:acc')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function acceptanceTasks(): void {
    $this->moduleInstaller->install(['joinup_acceptance']);
    $this->generateOauthKeys();
  }

  /**
   * Generates OAuth2 keys if they don't already exist.
   */
  #[CLI\Command(name: 'joinup:generate-oauth-keys')]
  #[CLI\Bootstrap(level: DrupalBootLevels::FULL)]
  public function generateOauthKeys(): void {
    $directory = 'private://simple_oauth';
    $realPath = $this->fileSystem->realpath($directory);

    if ($realPath && file_exists($realPath . '/public.key') && file_exists($realPath . '/private.key')) {
      $this->logger()->success(dt('OAuth2 keys already exist.'));
      return;
    }

    $this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS
    );

    $realPath = $this->fileSystem->realpath($directory);
    $this->keyGenerator->generateKeys($realPath);
    $this->logger()->success(dt('OAuth2 keys generated successfully.'));
  }

}
