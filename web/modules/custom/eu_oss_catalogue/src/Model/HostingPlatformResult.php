<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Model;

/**
 * Data model for code platforms repository results.
 */
class HostingPlatformResult {

  /**
   * Creates a new result object.
   *
   * @param string $namespace
   *   The namespace.
   * @param string $name
   *   The repository name.
   * @param string $url
   *   The repository URL.
   * @param string $defaultBranch
   *   The repository default branch.
   * @param string|null $latestReleaseTag
   *   The latest release tag.
   * @param string|null $latestReleaseDate
   *   The latest release date.
   * @param string|null $platformReference
   *   The code platform internal unique ID.
   * @param string|null $description
   *   The repository description if any.
   * @param string|null $file
   *   The content of a file if it has been requested.
   */
  public function __construct(
    public readonly string $namespace,
    public readonly string $name,
    public readonly string $url,
    public readonly string $defaultBranch,
    public readonly ?string $latestReleaseTag = NULL,
    public readonly ?string $latestReleaseDate = NULL,
    public readonly ?string $platformReference = NULL,
    public readonly ?string $description = NULL,
    public readonly ?string $file = NULL,
  ) {}

  /**
   * Gets the repository full-name.
   *
   * @return string
   *   Repository full name.
   */
  public function getFullName(): string {
    return $this->namespace . '/' . $this->name;
  }

}
