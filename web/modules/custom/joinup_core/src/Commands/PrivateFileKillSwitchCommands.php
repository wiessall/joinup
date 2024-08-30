<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Commands;

use Drupal\joinup_core\KillSwitchInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Private file kill switch commands.
 */
class PrivateFileKillSwitchCommands extends DrushCommands {

  public function __construct(protected KillSwitchInterface $killSwitch) {
    parent::__construct();
  }

  /**
   * Enable switch.
   */
  #[CLI\Command(name: 'joinup-core:kill-switch-remove')]
  #[CLI\Argument(name: 'type', description: 'The switch type')]
  public function killSwitchRemove(string $type): string {
    $this->killSwitch->remove($type);
    return dt('Removed @type file', [
      '@type' => $type,
    ]);
  }

  /**
   * Disable switch.
   */
  #[CLI\Command(name: 'joinup-core:kill-switch-create')]
  #[CLI\Argument(name: 'type', description: 'The switch type')]
  public function killSwitchCreate(string $type): string {
    $this->killSwitch->create($type);
    return dt('Created @type file', [
      '@type' => $type,
    ]);
  }

}
