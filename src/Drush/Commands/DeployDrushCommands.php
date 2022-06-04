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

}
