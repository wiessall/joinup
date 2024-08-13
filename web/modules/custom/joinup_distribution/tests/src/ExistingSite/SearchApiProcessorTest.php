<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_distribution\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;

/**
 * @covers \Drupal\joinup_distribution\Plugin\search_api\processor\IndexDistributionData::appendDistributionData
 * @group joinup_distribution
 */
class SearchApiProcessorTest extends JoinupExistingSiteTestBase {

  /**
   * Tests that a file having ftp:// as scheme can be indexed.
   */
  public function testFtpScheme(): void {
    $solution = $this->createRdfEntity([
      'rid' => 'solution',
      'field_is_state' => 'published',
      'label' => $this->randomMachineName(),
      // Ensure a parent collection.
      'collection' => $this->createRdfEntity([
        'rid' => 'collection',
        'label' => $this->randomString(),
        'field_ar_state' => 'published',
      ]),
    ]);
    $distribution = $this->createNode([
      'type' => 'distribution',
      'title' => $this->randomString(),
      'og_audience' => $solution->id(),
      'access_url' => 'ftp://example.com',
      'field_state' => 'published',
    ]);
    // Trigger indexing.
    \Drupal::getContainer()->get('search_api.post_request_indexing')->destruct();
    $this->assertSame('ftp://example.com', $distribution->get('access_url')->target_id);
  }

}
