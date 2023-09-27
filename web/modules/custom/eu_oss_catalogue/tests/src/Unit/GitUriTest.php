<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Unit;

use Drupal\Tests\UnitTestCaseTest;
use Drupal\eu_oss_catalogue\Model\GitUri;

/**
 * Tests GitUri.
 *
 * @coversDefaultClass \Drupal\eu_oss_catalogue\Model\GitUri
 * @group eu_oss_catalogue
 */
class GitUriTest extends UnitTestCaseTest {

  /**
   * Tests how object is instantiated from a URI string.
   *
   * @param string $uriString
   *   The URI string to test with.
   * @param array $expected
   *   The expected values in the GitUri object, as array.
   *
   * @covers ::fromUriString
   *
   * @dataProvider providerFromUriString
   */
  public function testFromUriString(string $uriString, ?array $expected): void {
    $gitUri = GitUri::fromUriString($uriString);
    if (!is_null($expected)) {
      $this->assertEquals(
        $expected,
        (array) GitUri::fromUriString($uriString)
      );
      return;
    }

    $this->assertNull($gitUri);
  }

  /**
   * Data provider for ::testFromUriString.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerFromUriString(): array {
    return [
      'Gitlab URL with deep namespace' => [
        'https://gitlab.opencode.de/xleitstelle/xplanung/validierungsregeln/profile/berlin/',
        [
          'scheme' => 'https',
          'host' => 'gitlab.opencode.de',
          'namespace' => 'xleitstelle/xplanung/validierungsregeln/profile',
          'package' => 'berlin',
        ],
      ],
      'Gitlab URL with deep namespace + .git extension' => [
        'URI' => 'https://gitlab.opencode.de/xleitstelle/xplanung/validierungsregeln/profile/berlin.git',
        'Expected object' => [
          'scheme' => 'https',
          'host' => 'gitlab.opencode.de',
          'namespace' => 'xleitstelle/xplanung/validierungsregeln/profile',
          'package' => 'berlin',
        ],
      ],
      'SSH URI with deep namespace' => [
        'git@gitlab.opencode.de:xleitstelle/xplanung/validierungsregeln/profile/berlin.git',
        [
          'scheme' => 'https',
          'host' => 'gitlab.opencode.de',
          'namespace' => 'xleitstelle/xplanung/validierungsregeln/profile',
          'package' => 'berlin',
        ],
      ],
      'SSH URI in subpath' => [
        'git@gitlab.opencode.de/xleitstelle:xplanung/validierungsregeln/profile/berlin.git',
        [
          'scheme' => 'https',
          'host' => 'gitlab.opencode.de/xleitstelle',
          'namespace' => 'xplanung/validierungsregeln/profile',
          'package' => 'berlin',
        ],
      ],
      'Partial URI' => [
        'URI' => 'http://foo.bar.baz',
        'Expected object' => [
          'scheme' => 'http',
          'host' => 'foo.bar.baz',
          'namespace' => NULL,
          'package' => NULL,
        ],
      ],
      'Invalid URI' => [
        'URI' => 'foo-bar-baz',
        'Expected object' => NULL,
      ],
    ];
  }

}
