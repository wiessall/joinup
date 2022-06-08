<?php

declare(strict_types = 1);

namespace Joinup\Drush\Commands;

use Drush\Commands\DrushCommands;

/**
 * Specific commands to run on Joinup code deployment.
 */
class DeployDrushCommands extends DrushCommands {

  /**
   * Rebuilds node access if case.
   *
   * @command joinup:node-access-rebuild
   * @bootstrap full
   */
  public function nodeAccessRebuild(): void {
    if (node_access_needs_rebuild()) {
      $this->say('Rebuilding node access... (patience, it takes a while)');
      node_access_rebuild();
      return;
    }
    $this->say("Node access doesn't need rebuild.");
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
   * @param array $modules
   *   A space separated list of installed modules whose codebase is gone.
   *
   * @command joinup:uninstall-orphan-modules
   * @bootstrap full
   * @usage joinup:uninstall-orphan-modules module1 module2
   *   Uninstalls module1 and module2
   */
  public function uninstallModules(array $modules): void {
    if (empty($modules)) {
      throw new \Exception('No modules passed.');
    }

    /** @var \Drupal\Core\Extension\ModuleExtensionList $extension_list */
    $extension_list = \Drupal::service('extension.list.module')->reset();
    $paths = $extension_list->getPathnames();
    $non_orphan_modules = array_filter($modules, function (string $module) use ($paths): bool {
      return isset($paths[$module]);
    });
    if ($non_orphan_modules) {
      throw new \Exception(sprintf('The codebase of %s module(s) is still present. Use generic Drush generic commands to uninstall them.', implode(', ', $non_orphan_modules)));
    }

    $db = \Drupal::database();
    $config = unserialize($db->select('config')
      ->fields('config', ['data'])
      ->condition('collection', '')
      ->condition('name', 'core.extension')
      ->execute()
      ->fetchField());

    $modules = array_combine($modules, $modules);
    if ($already_uninstalled = array_diff_key($modules, $config['module'])) {
      throw new \Exception(sprintf('Some modules are already uninstalled: %s. Aborting.', implode(', ', $already_uninstalled)));
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
  }

}
