<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\TwigExtension;

use Drupal\sparql_entity_storage\UriEncoder;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Adds SPARQL entity storage entities specific Twig filters and functions.
 *
 * @todo Move this to sparql_entity_storage in ISAICP-7374.
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7374
 */
class TwigExtension extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter('sparql_entity_storage_uri_encode', [
        $this,
        'sparqlEntityStorageUriEncode',
      ]),
      new TwigFilter('sparql_entity_storage_uri_decode', [
        $this,
        'sparqlEntityStorageUriDecode',
      ]),
    ];
  }

  /**
   * Encodes a URI with UriEncoder::encodeUrl().
   *
   * @param string $uri
   *   The URI.
   *
   * @return string
   *   The encoded URI.
   *
   * @see \Drupal\sparql_entity_storage\UriEncoder::encodeUrl()
   */
  public function sparqlEntityStorageUriEncode(string $uri): string {
    return UriEncoder::encodeUrl($uri);
  }

  /**
   * Decodes a URI encoded with UriEncoder::encodeUrl().
   *
   * @param string $encoded_uri
   *   The encoded URI.
   *
   * @return string
   *   The decoded URI.
   *
   * @see \Drupal\sparql_entity_storage\UriEncoder::encodeUrl()
   * @see \Drupal\sparql_entity_storage\UriEncoder::decodeUrl()
   */
  public function sparqlEntityStorageUriDecode(string $encoded_uri): string {
    return UriEncoder::decodeUrl($encoded_uri);
  }

}
