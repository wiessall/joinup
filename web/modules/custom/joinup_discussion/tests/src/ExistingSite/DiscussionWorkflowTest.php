<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_discussion\ExistingSite;

use Drupal\Tests\joinup_community_content\ExistingSite\CommunityContentWorkflowTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests CRUD operations and workflow transitions for the discussion node.
 */
#[Group('joinup_discussion')]
class DiscussionWorkflowTest extends CommunityContentWorkflowTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getEntityBundle(): string {
    return 'discussion';
  }

}
