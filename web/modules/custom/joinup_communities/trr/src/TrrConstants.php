<?php

declare(strict_types=1);

namespace Drupal\trr;

/**
 * A class of constants related to the TRR test fields functionality.
 */
class TrrConstants {

  /**
   * The TRR test values.
   *
   * @var string[]
   */
  const APPLICATION_COMPONENT = 'http://data.europa.eu/dr8/ConformanceTestingApplicationComponent';
  const APPLICATION_SERVICE = 'http://data.europa.eu/dr8/ConformanceTestingApplicationService';
  const BUSINESS_OBJECT = 'http://data.europa.eu/dr8/ConformanceTestScenarioBusinessObject';

  /**
   * The TRR test related fields.
   *
   * @var string[]
   */
  const TEST_RELATED_FIELDS = [
    'field_is_actor',
    'field_is_business_process',
    'field_is_product_type',
    'field_is_standardization_level',
    'field_is_test_resource_type',
  ];

  /**
   * Returns the test values as an array.
   *
   * @return string[]
   *   The test values.
   */
  public static function getTestValues(): array {
    return [
      self::APPLICATION_COMPONENT,
      self::APPLICATION_SERVICE,
      self::BUSINESS_OBJECT,
    ];
  }

}
