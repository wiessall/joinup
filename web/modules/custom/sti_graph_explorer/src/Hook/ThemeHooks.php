<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Theme registry entries for the explorer.
 */
final class ThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'sti_graph_explorer' => [
        'variables' => [],
      ],
    ];
  }

}
