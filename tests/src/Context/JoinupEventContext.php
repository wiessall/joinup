<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Hook\AfterScenario;
use Behat\Hook\BeforeScenario;
use Drupal\Core\Site\Settings;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Behat step definitions for testing events.
 */
class JoinupEventContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Keeps track of the number of Geocoding cache results that exist.
   *
   * @var int
   */
  protected static $geocodingCacheCount;

  /**
   * Converts relative dates into an accepted format.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   An object containing the entity properties and fields that are to be used
   *   for creating the node as properties on the object.
   *
   * @throws \Exception
   *   Thrown when an invalid format is specified for the dates.
   *
   * @BeforeNodeCreate
   */
  public static function massageEventFieldsBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'event') {
      return;
    }

    foreach (['field_event_date:value', 'field_event_date:end_value'] as $field) {
      if (isset($node->$field)) {
        $node->$field = date(DateTimeItemInterface::DATETIME_STORAGE_FORMAT, strtotime($node->$field));
      }
    }

    // In normal usage an event cannot be created without either a physical or
    // virtual location. If the scenario doesn't specify either of those,
    // provide a default value so that we don't run into unspecified behaviour.
    if (empty($node->field_location) && empty($node->field_event_online_location)) {
      $node->field_event_online_location = "0: No man's land - 1: http://www.no-mans-land-meetup.com";
    }
  }

  /**
   * Ensures that the file cache for Geocoder results is enabled.
   *
   * We are using the GISCO Geocoding service for geocoding event addresses
   * so they can be displayed as maps. In order to avoid requests to the service
   * during tests, we are using the File Cache module to cache the results of
   * API calls. These are being stored in the fixtures folder.
   *
   * @throws \Exception
   *   Thrown when the Geocoding results are not being cached.
   */
  #[BeforeScenario('@api')]
  public static function trackCachedFiles(): void {
    $cache_settings = Settings::get('cache');

    $geocoder_cache_backend = $cache_settings['bins']['geocoder'] ?? '';
    $geocoder_cache_directory = self::getGeocodingCacheDirectory();

    if ($geocoder_cache_backend !== 'cache.backend.file_system' || empty($geocoder_cache_directory)) {
      throw new \Exception('The Geocoding cache should be enabled. Please run ./vendor/bin/run drupal:settings site-clean');
    }

    // Keep track of the number of files that are present in the file cache.
    // After the feature has been completely tested we can check that the number
    // of files remains the same. This will ensure that no new requests have
    // been made to the Geocoding service without being saved in the file cache.
    static::$geocodingCacheCount = self::getGeocodingCacheCount();
  }

  /**
   * Verifies that all Geocoding requests are cached.
   *
   * @throws \Exception
   *   Thrown when live requests were done to the service during the test.
   */
  #[AfterScenario('@api')]
  public static function checkCachedFiles(AfterScenarioScope $scope): void {
    $expected_count = self::$geocodingCacheCount;

    if ($expected_count !== NULL && $expected_count != self::getGeocodingCacheCount()) {
      $feature = $scope->getFeature()->getFile();
      throw new \Exception("$feature is doing live requests to the Geocoding service. Please make sure to commit all cached requests in tests/fixtures/geocoding/cache.");
    }
  }

  /**
   * Returns the number of Geocoding results that have been cached.
   *
   * @return int
   *   The number of cached results.
   */
  protected static function getGeocodingCacheCount(): int {
    $directory = self::getGeocodingCacheDirectory();
    return iterator_count(new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS));
  }

  /**
   * Returns the path to the Geocoding file cache.
   *
   * @return string
   *   The location of the treasure.
   */
  protected static function getGeocodingCacheDirectory(): string {
    $filecache_settings = Settings::get('filecache');
    return $filecache_settings['directory']['bins']['geocoder'] ?? '';
  }

}
