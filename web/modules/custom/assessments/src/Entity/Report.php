<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

/**
 * Defines the report bundle entity class.
 */
final class Report extends Assessment {

  /**
   * Returns the default state value based on user permissions.
   *
   * @return array
   *   The default value array.
   */
  public static function getDefaultStateValue(): array {
    $current_user = \Drupal::currentUser();
    $state = $current_user->hasPermission('bypass assessment report moderation')
      ? 'published'
      : 'draft';

    return [['value' => $state]];
  }

}
