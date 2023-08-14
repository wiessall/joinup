<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Http\ClientFactory;
use GuzzleHttp\ClientInterface;

/**
 * Remote git API guesser.
 *
 * @internal
 */
final class ApiGuesser {

  protected ClientInterface $client;

  public function __construct(
    ClientFactory $client_factory
  ) {
    $this->client = $client_factory->fromOptions(['http_errors' => FALSE]);
  }

  public function findApiPlugin(string $repository_url): ?string {
    if (!UrlHelper::isValid($repository_url)) {
      throw new \InvalidArgumentException(
        sprintf(
          "The given repository URL parameter is not a valid URL: %s",
          $repository_url
        )
      );
    }

    // @todo move logic into plugins.
    if ($this->isGithub($repository_url)) {
      return 'github';
    }

    if ($this->isGitlab($repository_url)) {
      return 'gitlab';
    }

    if ($this->isBitbucket($repository_url)) {
      return 'bitbucket';
    }

    return NULL;
  }

  private function isBitbucket(string $repository_url): bool {
    $api_scheme = 'https';
    $api_host = NULL;
    $vendor = NULL;
    $package = NULL;

    if (UrlHelper::isExternal($repository_url)) {
      $parsed = parse_url($repository_url);
      $api_scheme= $parsed['scheme'] ?? NULL;
      $api_host_base = $parsed['host'];
      $path_parts = explode('/', $parsed['path'] ?? []);
      $vendor_package = array_slice($path_parts, -2);

      if (count($vendor_package) === 2) {
        [$vendor, $package] = $vendor_package;
        $api_host = $api_host_base . implode('/', array_slice($path_parts, 0, -2));
        if (str_ends_with($package, '.git')) {
          $package = substr($package, 0, -4);
        }
      }
    }


    // Example: git@foo.bar:vendor/package.git.
    // git@foo.bar.baz.eu/sub/path:ec-europa/digit-joinup-reference.git.
    if (
      str_starts_with($repository_url, 'git@') &&
      preg_match('#^git@(.*):([^/]*)/(.*)\.git$#', $repository_url, $matches)
    ) {
      [, $api_host, $vendor, $package] = $matches;
    }

    if (!$api_scheme || !$api_host || !$vendor || !$package) {
      return FALSE;
    }

    // Fetch https://git.fpfis.tech.ec.europa.eu/api/v4/projects/ec-europa%2Fdigit-joinup-reference
    // urlencode("$vendor/$project").
    $response = $this->client->request(
      'GET',
      "$api_scheme://$api_host/api/2.0/repositories/" . urlencode("$vendor/$package"),
      ['http_errors' => FALSE]
    );
    return $response->getStatusCode() === 200;
  }

  private function isGitlab(string $repository_url): bool {
    $api_scheme = 'https';
    $api_host = NULL;
    $vendor = NULL;
    $package = NULL;

    if (UrlHelper::isExternal($repository_url)) {
      $api_scheme= $parsed['scheme'] ?? NULL;
      $parsed = parse_url($repository_url);
      $api_host_base = $parsed['host'];
      $path_parts = explode('/', $parsed['path'] ?? []);
      $vendor_package = array_slice($path_parts, -2);

      if (count($vendor_package) === 2) {
        [$vendor, $package] = $vendor_package;
        $api_host = $api_host_base . implode('/', array_slice($path_parts, 0, -2));
        if (str_ends_with($package, '.git')) {
          $package = substr($package, 0, -4);
        }
      }
    }


    // Example: git@foo.bar:vendor/package.git.
    // git@foo.bar.baz.eu/sub/path:ec-europa/digit-joinup-reference.git.
    if (
      str_starts_with($repository_url, 'git@') &&
      preg_match('#^git@(.*):([^/]*)/(.*)\.git$#', $repository_url, $matches)
    ) {
      [, $api_host, $vendor, $package] = $matches;
    }

    if (!$api_scheme || !$api_host || !$vendor || !$package) {
      return FALSE;
    }

    // Fetch https://git.fpfis.tech.ec.europa.eu/api/v4/projects/ec-europa%2Fdigit-joinup-reference
    // urlencode("$vendor/$project").
    $response = $this->client->request(
      'GET',
      "$api_scheme://$api_host/api/v4/projects/" . urlencode("$vendor/$package"),
    );
    return $response->getStatusCode() === 200;
  }

  private function isGithub(string $repository_url): bool {
    // URLs like https://github.com/ec-europa/toolkit or
    // https://github.com/ec-europa/toolkit.git.
    $parsed = parse_url($repository_url);
    if (
      UrlHelper::isExternal($repository_url) &&
      $parsed['host'] === 'github.com' &&
      !empty($parsed['path']) &&
      preg_match('#^/([^\/]+)\/([^\/]+)$#', $parsed['path'])
    ) {
      return TRUE;
    }

    // Example: git@github.com:ec-europa/toolkit.git.
    if (
      str_starts_with($repository_url, 'git@github.com:') &&
      preg_match('#^git@github\.com:([^/]*)/(.*)\.git$#', $repository_url)
    ) {
      return TRUE;
    }

    return FALSE;
  }

}
