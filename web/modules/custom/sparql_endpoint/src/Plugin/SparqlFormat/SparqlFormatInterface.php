<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\sparql_endpoint\SparqlResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines an interface for SPARQL format plugins.
 */
interface SparqlFormatInterface extends PluginInspectionInterface {

  /**
   * Returns a Response object based on the provided SparqlResponse.
   *
   * @param \Drupal\sparql_endpoint\SparqlResponse $sparqlResponse
   *   The SparqlResponse object to process.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The generated response object.
   */
  public function getResponse(SparqlResponse $sparqlResponse): Response;

  /**
   * Determines whether the results should be displayed inline or not.
   *
   * @return bool
   *   Returns true if the results should be displayed inline, false otherwise.
   */
  public function isInlineResults(): bool;

}
