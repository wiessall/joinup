<?php

declare(strict_types = 1);

namespace Drupal\joinup_rdf\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\sparql_entity_storage\UriEncoder;
use Symfony\Component\Routing\Route;

/**
 * Provides an {decode_rdf_url} route param converter.
 */
class DecodeRdfUrlConverter implements ParamConverterInterface {

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return isset($definition['type']) && $definition['type'] === 'decode_rdf_url';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    return UriEncoder::decodeUrl($value);
  }

}
