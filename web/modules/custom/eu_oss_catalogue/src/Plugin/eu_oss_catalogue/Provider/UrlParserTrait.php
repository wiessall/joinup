<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\eu_oss_catalogue\Model\ApiGuess;

/**
 * Url parser trait.
 */
trait UrlParserTrait {

  /**
   * Parses a full URL into a namespace and name.
   *
   * The last segment becomes the name, and the rest form the namespace.
   *
   * @param string $fullUrl The full URL to process.
   *   Full URL
   * @param \Drupal\eu_oss_catalogue\Model\ApiGuess $api
   *   API guesser.
   *
   * @return array ['namespace' => string, 'name' => string]
   */
  /**
   * @param string $fullUrl
   * @param \Drupal\eu_oss_catalogue\Model\ApiGuess $api
   *
   * @return array
   */
  protected function splitUrlIntoNamespaceAndName(string $fullUrl, ApiGuess $api): array {
    $relativePath = trim(substr($fullUrl, strlen($api->baseUrl)), '/');
    $pathParts = explode('/', $relativePath);
    $name = array_pop($pathParts);
    $namespace = implode('/', $pathParts);

    return [
      'namespace' => $namespace,
      'name' => $name,
    ];
  }

}
