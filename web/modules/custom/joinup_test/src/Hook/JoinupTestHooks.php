<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Joinup\TestMode;
use Joinup\TestModeManager;

/**
 * Hook implementations for the Joinup Test module.
 */
class JoinupTestHooks {

  /**
   * Implements hook_page_top().
   */
  #[Hook('page_top', order: new OrderAfter(modules: ['toolbar']))]
  public function pageTop(array &$page_top): void {
    // Remove the toolbar during Playwright tests.
    if (TestModeManager::current() === TestMode::Playwright) {
      unset($page_top['toolbar']);
    }
  }

}
