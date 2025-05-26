<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model;

use Drupal\Component\Utility\UrlHelper;

/**
 * Object storing a git URL.
 */
final class GitUri {

  public function __construct(
    public readonly string $scheme,
    public readonly string $host,
    public readonly ?string $namespace = NULL,
    public readonly ?string $name = NULL,
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

    $scheme = NULL;
    $host = NULL;
    $namespace = NULL;
    $name = NULL;

    // Example: https://gihub.com/drupal/drupal-driver or
    // https://bitbucket.selfhosted.com/atlassian/docker-browse.git
    if (UrlHelper::isExternal($repositoryUrl)) {
      $parsed = parse_url($repositoryUrl);
      $scheme = $parsed['scheme'] ?? NULL;
      $host = $parsed['host'];
      $pathParts = array_filter(explode('/', $parsed['path'] ?? ''));

      if (count($pathParts) > 1) {
        $name = array_pop($pathParts);
        $namespace = implode('/', $pathParts);
        if (str_ends_with($name, '.git')) {
          $name = substr($name, 0, -4);
        }
      }
    }

    if (!is_string($scheme) || !is_string($host) || !$name || !$namespace) {
      return NULL;
    }

    return new self($scheme, $host, $namespace, $name);
  }

  /**
   * Returns the "scheme and host" part of the URL.
   *
   * @return string
   *   The scheme and host.
   */
  public function getSchemeAndHost(): string {
    return $this->scheme . '://' . $this->host;
  }

  /**
   * Returns the cleaned URL (without any ".git" suffix).
   *
   * @return string
   *   The cleaned git URL.
   */
  public function getUri(): string {
    return implode('/', array_filter([
      $this->getSchemeAndHost(),
      $this->namespace,
      $this->name,
    ]));
  }

}
