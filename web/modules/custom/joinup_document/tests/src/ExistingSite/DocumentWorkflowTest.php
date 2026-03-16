<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_document\ExistingSite;

use Drupal\Tests\joinup_community_content\ExistingSite\CommunityContentWorkflowTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests CRUD operations and workflow transitions for the document node.
 */
#[Group('workflow')]
class DocumentWorkflowTest extends CommunityContentWorkflowTestBase {

  /**
   * {@inheritdoc}
   */
  protected function getEntityBundle(): string {
    return 'document';
  }

}
