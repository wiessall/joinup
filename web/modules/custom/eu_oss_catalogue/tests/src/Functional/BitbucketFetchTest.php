<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Bitbucket code hosting provider.
 */
#[Group('eu_oss_catalogue')]
class BitbucketFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['BSD-3-Clause'],
    'oss_category' => ['knowledge-management'],
  ];

  /**
   * Tests fetching all solutions from a Bitbucket platform.
   */
  public function testSelfHostedAll(): void {
    $this->fetchSolutions('hosting_platform:bitbucket_test', 1);
    $this->assertOssSolution('Bar Project', 'it');

    // Check file not found log entires.
    $notFoundLogEntries = array_map(
      function (object $log) {
        $logArray = (array) $log;
        $logArray['variables'] = unserialize($logArray['variables'], ['allowed_classes' => FALSE]);
        return $logArray;
      },
      $this->getLogEntries("Cannot find the '%filename' file in '%repository' on '%base_url' in branch / tag / commit '%reference'.")
    );

    $this->assertEquals(
      [
        [
          'message' => "Cannot find the '%filename' file in '%repository' on '%base_url' in branch / tag / commit '%reference'.",
          'variables' => [
            '%filename' => 'publiccode.yml',
            '%repository' => 'test_namespace/baz',
            '%base_url' => 'https://bitbucket.org',
            '%reference' => 'master',
          ],
        ],
        [
          'message' => "Cannot find the '%filename' file in '%repository' on '%base_url' in branch / tag / commit '%reference'.",
          'variables' => [
            '%filename' => 'https://bitbucket.org/test_namespace/bar/assets/Bar-Screenshot-3.png',
            '%repository' => 'test_namespace/bar',
            '%base_url' => 'https://bitbucket.org',
            '%reference' => 'master',
          ],
        ],
      ],
      $notFoundLogEntries
    );
  }

}
