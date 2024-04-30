<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\sparql_entity_storage\UriEncoder;
use Symfony\Component\Routing\Route;

/**
 * Provides an {decode_rdf_url} route param converter.
 *
 * Some modules expected to get entity id (string, not object) as part of URL,
 * ex flag module. Rdf entity id can contain special characters,
 * so it needs to be encoded in order to be used in URL.
 * Encoded entity id will not be found by module which expected
 * to get "real" entity id. This param converter resolves this problem.
 * It decodes earlier encoded RDF id.
 *
 * @see joinup_like.routing.yml as example.
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
