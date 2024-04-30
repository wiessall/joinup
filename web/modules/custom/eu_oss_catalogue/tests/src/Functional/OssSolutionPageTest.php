<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * Tests the OSS solution page.
 *
 * @group eu_oss_catalogue
 */
class OssSolutionPageTest extends FetchTestBase {

  /**
   * Tests node view access.
   *
   * @param int $archive
   *   Archive value.
   * @param int $code
   *   Status code for a not logged in user.
   *
   * @dataProvider viewDataProvider
   */
  public function testViewAccess(int $archive, int $code): void {
    $node = $this->createNode([
      'type' => 'oss_solution',
      'status' => 1,
      'oss_archived' => $archive,
    ]);

    // Anonymous user.
    $assertSession = $this->assertSession();
    $this->drupalGet($node->toUrl());
    $assertSession->statusCodeEquals($code);

    // OSS administrator.
    $adminUser = $this->drupalCreateUser(['administer eu oss catalogue']);
    $this->drupalLogin($adminUser);
    $this->drupalGet($node->toUrl());
    $assertSession->statusCodeEquals(200);
  }

  /**
   * Data provider for testViewAccess.
   */
  public function viewDataProvider(): array {
    return [
      [0, 200],
      [1, 403],
    ];
  }

}
