<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Component\Plugin\PluginBase;
use Drupal\sparql_endpoint\SparqlResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Represents a base SPARQL format.
 *
 * This class extends the Symfony PluginBase class and implements the
 * SparqlFormatInterface. It provides the necessary methods to work with SPARQL
 * format.
 */
abstract class BaseSparqlFormat extends PluginBase implements SparqlFormatInterface {

  /**
   * {@inheritdoc}
   */
  public function getResponse(SparqlResponse $sparqlResponse): Response {
    return new Response($sparqlResponse->results, $sparqlResponse->code, $this->getHeaders());
  }

  /**
   * Gets headers.
   *
   * @return string[]
   *   Response headers.
   */
  protected function getHeaders(): array {
    return [
      'Content-Type' => $this->getPluginId(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isInlineResults(): bool {
    return (bool) $this->pluginDefinition['inline'] ?? FALSE;
  }

}
