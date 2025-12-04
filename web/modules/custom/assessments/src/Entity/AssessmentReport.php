<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\AssessmentInterface;

/**
 * Bundle class for assessment reports.
 */
class AssessmentReport extends Assessment implements AssessmentInterface {

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
