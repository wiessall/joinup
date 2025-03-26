<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Drush\Commands;

use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Commands\sql\sanitize\SanitizeCommands;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Overwrites Webform Drush sanitization plugins.
 *
 * @see \Drush\Commands\sql\sanitize\SanitizeCommands::sanitize()
 */
class WebformAssessmentCommands extends DrushCommands {

  /**
   * Disables default webform sanitization.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   Input.
   *
   * @see \Drupal\webform\Commands\WebformSanitizeSubmissionsCommands::sanitize()
   */
  #[CLI\Hook(type: HookManager::INITIALIZE, target: 'sql-sanitize')]
  public function disableDefaultWebformSanitization(InputInterface $input): void {
    // @see \Drupal\webform\Commands\WebformSanitizeSubmissionsCommands::sanitize()
    $input->setOption('sanitize-webform-submissions', 'no');
  }

}
