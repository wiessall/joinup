<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Model;

use Drupal\Component\Utility\UrlHelper;

/**
 * Object storing a git URL.
 */
final class GitUri {

  /**
   * Constructs a new GitUri instance.
   *
   * @param string $scheme
   *   The scheme of the git URL.
   * @param string $host
   *   The host of the git URL. Might be only a domain, but also a domain with
   *   a path like "repository.example.com/self-hosted-gitlab".
   * @param string|null $namespace
   *   The namespace of the git package.
   * @param string|null $package
   *   The name of the package.
   */
  public function __construct(
    public readonly string $scheme,
    public readonly string $host,
    public readonly ?string $namespace = NULL,
    public readonly ?string $package = NULL,
  ) {}

  /**
   * Creates a GitUri instance from a URI string.
   *
   * @param string $repositoryUri
   *   The URI string to parse.
   *
   * @return self|null
   *   A GitUri instance if the URI string might be a git URL, NULL otherwise.
   */
  public static function fromUriString(string $repositoryUri): ?self {
    $repositoryUrl = trim($repositoryUri, ' /');
    if (!UrlHelper::isValid($repositoryUrl)) {
      return NULL;
    }
    // We can't get scheme from "git@..." URIs. Assume https.
    $scheme = 'https';
    $host = NULL;
    $namespace = NULL;
    $package = NULL;

    // Example: https://gihub.com/drupal/drupal-driver or
    // https://bitbucket.selfhosted.com/atlassian/docker-browse.git
    if (UrlHelper::isExternal($repositoryUrl)) {
      $parsed = parse_url($repositoryUrl);
      $scheme = $parsed['scheme'] ?? NULL;
      $host = $parsed['host'];
      $pathParts = array_filter(
        explode('/', $parsed['path'] ?? ''),
        fn (string $part): bool => $part !== ''
      );

      if (count($pathParts) > 2) {
        $package = array_pop($pathParts);
        $namespace = implode('/', $pathParts);
        if (str_ends_with($package, '.git')) {
          $package = substr($package, 0, -4);
        }
      }
    }

    // Example: git@foo.bar:namespace/package.git.
    // git@foo.bar.baz.eu/sub/path:ec-europa/digit-joinup-reference.git.
    if (
      str_starts_with($repositoryUrl, 'git@') &&
      preg_match('#^git@(.*):(.*)/([^/]*)\.git$#', $repositoryUrl, $matches)
    ) {
      [, $host, $namespace, $package] = $matches;
    }

    if (!is_string($scheme) || !is_string($host)) {
      return NULL;
    }

    return new static($scheme, $host, $namespace, $package);
  }

  /**
   * Determines whether the git URL is complete.
   *
   * @return bool
   *   Whether the current instance represents a URL to a specific package or
   *   not.
   */
  public function isComplete(): bool {
    return (bool) array_filter(
      [$this->namespace, $this->package],
      fn ($property): bool => !is_null($property) && $property !== ''
    );
  }

}
