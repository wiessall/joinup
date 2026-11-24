<?php

declare(strict_types=1);

namespace Drupal\sti_ui\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Moves Joinup's collection management tasks into the moderation hub.
 */
final class StiUiHooks {

  /**
   * Implements hook_local_tasks_alter().
   *
   * "Unpublished content" is a primary task in joinup_group; here it becomes
   * a tab next to "Content". Altered rather than patched: the distribution's
   * definition stays as it is.
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(array &$tasks): void {
    if (isset($tasks['joinup_group.unpublished_content'], $tasks['joinup.moderate_content'])) {
      $tasks['joinup_group.unpublished_content']['parent_id'] = 'joinup.moderate_content';
      $tasks['joinup_group.unpublished_content']['weight'] = 30;
    }
  }

}
