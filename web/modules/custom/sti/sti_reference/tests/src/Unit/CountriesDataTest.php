<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_reference\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Lookups by code trust these codes. A code on the wrong country is silent: data about Sudan landed on South
 * Sudan, Guinea on Equatorial Guinea and Samoa on American Samoa until November 2026.
 *
 * @group sti_reference
 */
final class CountriesDataTest extends UnitTestCase {

  public function testEveryCodeIsPresentAndUnique(): void {
    $countries = json_decode(file_get_contents(__DIR__ . '/../../../data/countries.json'), TRUE, flags: JSON_THROW_ON_ERROR);
    foreach (['iso_code' => '/^[A-Z]{3}$/', 'iso_code_2' => '/^[A-Z]{2}$/', 'm49_code' => '/^\d{3}$/'] as $field => $pattern) {
      $seen = [];
      foreach ($countries as $country) {
        $code = $country[$field] ?? '';
        $this->assertMatchesRegularExpression($pattern, $code, "{$country['name']}: $field");
        $this->assertArrayNotHasKey($code, $seen, "$field $code is on both {$country['name']} and " . ($seen[$code] ?? ''));
        $seen[$code] = $country['name'];
      }
    }
  }

}
