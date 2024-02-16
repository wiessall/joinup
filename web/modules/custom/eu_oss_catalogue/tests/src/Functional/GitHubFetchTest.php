<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * Fetch data from GitHub test.
 *
 * @group eu_oss_catalogue
 */
class GitHubFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause'],
    'oss_category' => ['website-builder', 'digital-citizenship'],
  ];

  /**
   * Test GitHub hosting platform.
   */
  public function testGitHubHostingPlatform(): void {
    $this->fetchSolutions('hosting_platform:github_mockup', 2);

    $this->assertOssSolution('GitHub solution 1', 'en');
    $this->assertOssContact('FOSS Conference');
    $this->assertOssContact('Pirate Initiative');

    $this->assertOssSolution('GitHub solution 2', 'it');
    $this->assertOssContractor('ACME Ltd');
    $this->assertOssContractor('Das Auto GmbH');
    $this->assertOssContact('Peter Gregory');

    // Check that 'logo not found' exception has been logged.
    $notFoundLogEntries = $this->getLogEntries(
      '%type: @message in %function (line %line of %file).',
      'a:6:{s:5:"%type";s:36:"GuzzleHttp\Exception\ClientException";s:8:"@message";s:153:"Client error: `HEAD https://raw.githubusercontent.com/dummy-namespace-2/dummy-name-2/HEAD/path/to/images/logo.jpg` resulted in a `404 Not Found` response";',
    );
    $this->assertCount(1, $notFoundLogEntries);
  }

}
