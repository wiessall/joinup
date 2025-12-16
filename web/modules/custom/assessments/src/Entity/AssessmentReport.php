<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\AssessmentInterface;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;

/**
 * Bundle class for assessment reports.
 */
class AssessmentReport extends Assessment implements AssessmentInterface, EntityWorkflowStateInterface {

  use EntityWorkflowStateTrait;
  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'state';
  }

  /**
   * Returns the default state value based on user permissions.
   *
   * @return array
   *   The default value array.
   */
  public static function getDefaultStateValue(): array {
    // @todo ISAICP-10620: Remove/clarify this.
    $current_user = \Drupal::currentUser();
    $state = $current_user->hasPermission('bypass assessment report moderation')
      ? 'published'
      : 'draft';

    return [['value' => $state]];
  }

}
