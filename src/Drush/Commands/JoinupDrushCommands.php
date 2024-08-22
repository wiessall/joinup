<?php

declare(strict_types=1);

namespace Joinup\Drush\Commands;

use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ExtensionDiscovery;
use Drupal\upgrade_status\ProjectCollector;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

/**
 * Specific commands to run on Joinup code deployment.
 */
class JoinupDrushCommands extends DrushCommands {

  /**
   * Flushes all Memcache backends.
   *
   * This command is designed to avoid using a bootstrapped Drupal, in order to
   * be used in circumstances when Drupal is not yet installed.
   */
  #[CLI\Command(name: 'memcache:flush-all')]
  #[CLI\Bootstrap(level: DrupalBootLevels::NONE)]
  public function memcacheFlushAll(): void {
    if (!$memcached_hosts = getenv('DRUPAL_MEMCACHE_HOSTS')) {
      $this->logger()->warning('The DRUPAL_MEMCACHE_HOSTS environment variable is empty or not set');
      return;
    }
    $memcached = new \Memcached();
    // The expected DRUPAL_MEMCACHE_HOSTS pattern is:
    // @code
    // DRUPAL_MEMCACHE_HOSTS=host1:port1,host2:port2,...
    // @endcode
    foreach (explode(',', $memcached_hosts) as $memcached_host) {
      [$host, $port] = explode(':', $memcached_host);
      $memcached->addServer($host, (int) $port);
    }

    if ($memcached->getStats() === FALSE) {
      throw new \RuntimeException('Memcached service is not available');
    }

    $memcached->flush();
    $this->logger()->notice('Memcache backend flushed');
  }

  /**
   * Install modules.
   *
   * @param array $modules
   *   List of modules.
   *
   * @command joinup:install
   * @bootstrap full
   */
  public function installModules(array $modules): void {
    /** @var \Drush\Drupal\Commands\pm\PmCommands $original */
    $original = \Drupal::service('pm.commands');
    $original->install($modules);
  }

  /**
   * Rebuilds node access if case.
   *
   * @command joinup:node-access-rebuild
   * @bootstrap full
   */
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
    $sapiTaskManager = \Drupal::service('search_api.task_manager');
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
   *
   * @command joinup:acc
   * @bootstrap full
   */
  public function acceptanceTasks(): void {
    \Drupal::service('module_installer')->install(['joinup_acceptance']);
  }

  /**
   * Forcefully uninstalls modules whose codebase is gone.
   *
   * This command should be used with caution, only for very edge cases because
   * it might leave the system in an unstable state.
   *
   * VERY IMPORTANT aspects to consider:
   * - It doesn't run any uninstall hooks.
   * - It doesn't run the uninstallation validators.
   * - It doesn't uninstall dependencies. Dependent modules should be explicitly
   *   added to the list.
   * - It doesn't remove the schema tables. Those tables should be explicitly
   *   deleted after the command run.
   * - It doesn't clean up entity bundles (including fields) of every entity
   *   type provided by the module that is being uninstalled.
   * - It doesn't remove configuration belonging to the module.
   * - It doesn't remove any cache bin defined by the module.
   * - The router is not rebuilt. If some modules are defining routes, then a
   *   route rebuild should be issued just after this command.
   * - Passing a module whose codebase still exists, is not possible. The Drush
   *   standard way to uninstall should be used for such modules.
   * That said, the user issuing this command is responsible to check and ensure
   * that all the above points are properly addressed.
   *
   * Note that this command is only bootstrapping the database to avoid any
   * interaction with the container, which might be damaged due to missing
   * modules code.
   *
   * @param array $modules
   *   A space separated list of installed modules whose codebase is gone.
   *
   * @command joinup:force-uninstall-modules
   *
   * @bootstrap database
   * @usage joinup:force-uninstall-modules module1 module2
   *   Uninstalls module1 and module2
   */
  public function uninstallModules(array $modules): void {
    if (empty($modules)) {
      throw new \Exception('No modules passed.');
    }

    if ($modules_with_code = array_intersect($modules, $this->scanFileSystemModules())) {
      $this->logger()->error(dt('The codebase of !modules module(s) is still present. Use Drush pm:uninstall command to uninstall them.', [
        '!modules' => implode(', ', $modules_with_code),
      ]));
      return;
    }

    $db = Database::getConnection();
    $config = unserialize($db->select('config')
      ->fields('config', ['data'])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute()
      ->fetchField(), ['allowed_classes' => FALSE]);

    $modules = array_combine($modules, $modules);
    if ($already_uninstalled_modules = array_diff_key($modules, $config['module'])) {
      $this->logger()->error(dt('Module(s) !modules are already uninstalled.', [
        '!modules' => implode(', ', $already_uninstalled_modules),
      ]));
      return;
    }

    $config['module'] = array_diff_key($config['module'], $modules);
    $db->update('config')
      ->fields(['data' => serialize($config)])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute();

    $db->delete('key_value')
      ->condition('collection', 'system.schema')
      ->condition('name', $modules, 'IN')
      ->execute();

    $this->logger()->success(dt('Forcefully uninstalled !modules modules(s).', [
      '!modules' => implode(', ', $modules),
    ]));
  }

  /**
   * Returns all modules from codebase regardless if they are installed or not.
   *
   * @return string[]
   *   A list of modules.
   */
  protected function scanFileSystemModules(): array {
    $discovery = new ExtensionDiscovery(DRUPAL_ROOT, FALSE, ['profiles/joinup']);
    return array_keys($discovery->scan('module', FALSE));
  }

  /**
   * Checks for extensions metadata deprecations.
   *
   * Uses the Upgrade Status API to check only the extensions metadata
   * deprecations, such as outdated 'core_version_requirement' *.info.yml
   * property.
   *
   * @command joinup:check-extension-metadata-deprecation
   *
   * @option custom Scan custom extensions.
   * @option contrib Scan contrib extensions.
   * @option uninstalled Scan uninstalled extensions.
   *
   * @bootstrap full
   * @validate-module-enabled upgrade_status
   */
  public function checkExtensionMetadataDeprecation(
    $options = [
      'custom' => TRUE,
      'contrib' => FALSE,
      'uninstalled' => TRUE,
    ],
  ): void {
    /** @var \Drupal\upgrade_status\ExtensionMetadataDeprecationAnalyzer $analizer */
    $analizer = \Drupal::service('upgrade_status.extension_metadata_deprecation_analyzer');
    /** @var \Drupal\upgrade_status\ProjectCollector $project_collector */
    $project_collector = \Drupal::service('upgrade_status.project_collector');
    $projects = $project_collector->collectProjects();
    $deprecations = [];
    foreach ($projects as $project) {
      if (!$options['uninstalled'] && !$project->status) {
        continue;
      }
      if (!$options['contrib'] && $project->info['upgrade_status_type'] === ProjectCollector::TYPE_CONTRIB) {
        continue;
      }
      if (!$options['custom'] && $project->info['upgrade_status_type'] === ProjectCollector::TYPE_CUSTOM) {
        continue;
      }

      foreach ($analizer->analyze($project) as $deprecation) {
        $deprecations[] = "{$deprecation->getFile()}: {$deprecation->getLine()}\n{$deprecation->getMessage()}";
      }
    }
    if ($deprecations) {
      throw new \Exception(implode("\n", $deprecations));
    }
  }

}
