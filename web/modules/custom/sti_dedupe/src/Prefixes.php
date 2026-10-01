<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\sti_rdf\StiUri;

/**
 * Compacts predicate URIs for display.
 */
final class Prefixes {

  private const MAP = [
    StiUri::ATIO => 'atio:',
    StiUri::STI => 'sti:',
    'http://purl.org/dc/terms/' => 'dct:',
    'http://www.w3.org/ns/dcat#' => 'dcat:',
    'http://xmlns.com/foaf/0.1/' => 'foaf:',
    'http://www.w3.org/2004/02/skos/core#' => 'skos:',
    'http://www.w3.org/ns/prov#' => 'prov:',
  ];

  /**
   * Returns "prefix:local" for a known namespace, else the URI unchanged.
   */
  public static function compact(string $uri): string {
    foreach (self::MAP as $namespace => $prefix) {
      if (str_starts_with($uri, $namespace)) {
        return $prefix . substr($uri, strlen($namespace));
      }
    }
    return $uri;
  }

}
