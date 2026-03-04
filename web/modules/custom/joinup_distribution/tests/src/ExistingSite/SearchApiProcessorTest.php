<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_distribution\ExistingSite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\joinup_test\Traits\ParagraphTrait;
use Drupal\joinup_distribution\Entity\DistributionInterface;

#[CoversClass(\Drupal\joinup_distribution\Plugin\search_api\processor\IndexDistributionData::appendDistributionData)]
#[Group('joinup_distribution')]
class SearchApiProcessorTest extends JoinupExistingSiteTestBase {

  use ParagraphTrait;

  /**
   * Tests that a file having ftp:// as scheme can be indexed.
   */
  public function testFtpScheme(): void {
    $solution = $this->createNode([
      'type' => 'solution',
      'field_state' => 'published',
      'title' => $this->randomMachineName(),
      // Ensure a parent collection.
      'og_audience' => $this->createNode([
        'type' => 'collection',
        'title' => $this->randomString(),
        'field_state' => 'published',
      ]),
    ]);
    $paragraph = $this->createParagraph([
      'type' => 'resource_link',
      'remote_file_url' => [
        [
          'uri' => 'ftp://example.com',
        ],
      ],
    ]);
    $distribution = $this->createNode([
      'type' => 'distribution',
      'title' => $this->randomString(),
      'og_audience' => $solution->id(),
      'resources' => ['target_id' => $paragraph->id(), 'target_revision_id' => $paragraph->getRevisionId()],
      'field_state' => 'published',
    ]);
    // Trigger indexing.
    \Drupal::getContainer()->get('search_api.post_request_indexing')->destruct();
    if (!$distribution instanceof DistributionInterface) {
      return;
    }
    $this->assertSame('ftp://example.com', $distribution->getResources()[0]->uri);
  }

}
