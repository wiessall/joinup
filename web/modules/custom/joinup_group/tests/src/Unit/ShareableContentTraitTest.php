<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_group\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\joinup_group\Entity\ShareableContentTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests ShareableContentTrait.
 */
#[Group('joinup_group')]
#[CoversClass(ShareableContentTrait::class)]
class ShareableContentTraitTest extends UnitTestCase {

  /**
   * Tests special datetime calculation in ShareableContentTraitTest.
   *
   * @param string $initial
   *   A datetime string used later as the initial timestamp. This is coverted
   *   to timestamp. Please use Y-n-j format for clarity.
   * @param int $months
   *   Number of months to add to the test timestamp.
   * @param string $expected
   *   The expected result, also inY-n-j format.
   */
  #[DataProvider('providerTest')]
  public function testSomeMonthLater(string $initial, int $months, string $expected): void {
    $timestamp = (new \DateTime("$initial", new \DateTimeZone('UTC')))->getTimestamp();

    $laterTimestampActual = MonthsLaterTestClass::someMonthsLater($months, $timestamp);

    // Timestamp to date string conversion id for us, humans.
    $this->assertEquals(
      $expected,
      (new \DateTime("@$laterTimestampActual"))->format('Y-n-j')
    );
  }

  /**
   * Test data provider for ::testSomeMonthLater.
   *
   * @return array[]
   *   The test cases.
   */
  public static function providerTest(): array {
    return [
      'one month starting in November' => [
        '2024-11-30',
        1,
        '2024-12-30',
      ],
      '2 months, starting in November' => [
        '2024-11-30',
        2,
        '2025-1-30',
      ],
      '2 months, starting at the end of Dec, ending in Feb' => [
        '2024-12-31',
        2,
        '2025-3-3',
      ],
      '11 months, starting on the end of the year' => [
        '2024-12-31',
        11,
        '2025-12-1',
      ],
      '12 months, starting in Dec' => [
        '2024-12-31',
        12,
        '2025-12-31',
      ],
      '37 months' => [
        '2024-12-31',
        37,
        '2028-1-31',
      ],
    ];
  }

}

/**
 * Test class using ShareableContentTrait used for testing the trait.
 */
class MonthsLaterTestClass {

  use ShareableContentTrait {
    someMonthsLater as public;
  }

}
