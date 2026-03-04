<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\token\Functional\TokenTestTrait;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the community content parent URL tokens.
 */
class TokenTest extends JoinupExistingSiteTestBase {

  // Use NodeCreationTrait;.
  use TokenTestTrait;

  /**
   * Tests the 'short_id_or_title' token.
   *
   * @param string $bundle
   *   The entity bundle.
   * @param string $short_id
   *   The value of the short ID field.
   * @param string $title
   *   The title of the entity.
   * @param string $expected_token
   *   The expected token replacement for the 'short_id_or_title'.
   */
  #[DataProvider('shortIdOrTitleDataProvider')]
  public function testShortIdOrTitleToken(string $bundle, string $short_id, string $title, string $expected_token): void {
    $entity = Node::create([
      'type' => $bundle,
      'title' => $title,
      'short_id' => $short_id,
    ]);

    $tokens = [
      'short_id_or_title' => $expected_token,
    ];
    $data = ['node' => $entity];
    $this->assertTokens('node', $data, $tokens);
  }

  /**
   * Tests that 'short_id_or_title' does not fail for bundles without short ID.
   */
  public function testShortIdOrTitleInvalidBundles(): void {
    $title = $this->randomMachineName();
    $entity = Node::create([
      'type' => 'release',
      'title' => $title,
    ]);

    $tokens = [
      'short_id_or_title' => $title,
    ];
    $data = ['node' => $entity];
    $this->assertTokens('node', $data, $tokens);
  }

  /**
   * Provides data for the testShortIdOrTitleToken method.
   */
  public static function shortIdOrTitleDataProvider(): array {
    return [
      'solution with a short ID' => [
        'solution', 'some-short-id', 'some title', 'some-short-id',
      ],
      'solution without a short ID' => [
        'solution', '', 'some title', 'some title',
      ],
      'collection with a short ID' => [
        'collection', 'some-short-id', 'some title', 'some-short-id',
      ],
      'collection without a short ID' => [
        'collection', '', 'some title', 'some title',
      ],
    ];
  }

}
