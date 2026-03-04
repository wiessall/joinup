<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_event\ExistingSite;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\joinup_community_content\ExistingSite\CommunityContentWorkflowTestBase;

/**
 * Tests CRUD operations and workflow transitions for the event node.
 */
#[Group('workflow')]
class EventWorkflowTest extends CommunityContentWorkflowTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getEntityBundle(): string {
    return 'event';
  }

}
