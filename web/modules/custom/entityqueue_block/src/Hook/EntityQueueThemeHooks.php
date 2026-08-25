<?php

declare(strict_types=1);

namespace Drupal\entityqueue_block\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for entityqueue_block theme.
 */
final class EntityQueueThemeHooks {

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      // Normally theme suggestion templates are only picked up when they are in
      // themes. We explicitly define a theme suggestion here so that the block
      // template in the templates/ folder is picked up.
      'block__entityqueue_block' => [
        'render element' => 'elements',
        'base hook' => 'block',
      ],
    ];
  }

}
