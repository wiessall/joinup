<?php

declare(strict_types = 1);

namespace Drupal\oe_gisco_geocoding\Plugin\Geocoder\Provider;

use Drupal\geocoder\ConfigurableProviderUsingHandlerWithAdapterBase;

/**
 * GISCO Geocoding Provider for the Geocoder module.
 *
 * @GeocoderProvider(
 *   id = "gisco_geocoding",
 *   name = "GISCO Geocoding",
 *   handler = "\OpenEuropa\Provider\GiscoGeocoding\GiscoGeocodingProvider",
 *   arguments = {
 *     "referer" = NULL,
 *   },
 * )
 */
class GiscoGeocoding extends ConfigurableProviderUsingHandlerWithAdapterBase {
}
