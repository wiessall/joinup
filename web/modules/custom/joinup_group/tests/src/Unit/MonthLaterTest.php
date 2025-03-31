<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_group\Unit;

use Drupal\joinup_group\Entity\ShareableContentTrait;
use Drupal\Tests\UnitTestCase;

class ShareableContentTraitTest extends UnitTestCase {

  /**
   * @param string $initial
   * @param int $months
   * @param string $expected
   *
   * @dataProvider providerTest
   */
  public function testSomeMonthLater(string $initial, int $months, string $expected): void {
    $timestamp = (new \DateTime("$initial", new \DateTimeZone('UTC')))->getTimestamp();

    $laterTimestamp = MonthsLaterTestClass::someMonthsLater($months, $timestamp);

    $this->assertEquals(
      $expected,
      (new \DateTime("@$laterTimestamp"))->format('Y-n-j')
    );
  }

  public static function providerTest(): array {
    return [
      'start 11, one month' => [
        '2024-11-30',
        1,
        '2024-12-30',
      ],
      'start 11, 2 month' => [
        '2024-11-30',
        2,
        '2025-1-30',
      ],
      'start 12, 2 month' => [
        '2024-12-31',
        2,
        '2025-2-28',
      ],
      'start 12, 11 month' => [
        '2024-12-31',
        11,
        '2025-11-30',
      ],
      'start 12, 12 month' => [
        '2024-12-31',
        12,
        '2025-12-31',
      ],
      'start 12, 37 month' => [
        '2024-12-31',
        37,
        '2028-1-31',
      ],
    ];
  }

}

class MonthsLaterTestClass {

  use ShareableContentTrait {
    someMonthsLater as public;
  }

}
