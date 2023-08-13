<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_platform_github;

use Drupal\eu_oss_catalogue\HostingPlatformResultInterface;

/**
 * Data model for GitHub repository fetch results.
 */
class GitHubResult implements HostingPlatformResultInterface {

  /**
   * Creates a nes result object.
   *
   * @param string $vendor
   *   The vendor ID.
   * @param string $name
   *   The repository name.
   * @param string $defaultBranch
   *   The repository default branch.
   * @param string $platformReference
   *   The GitHub internal unique ID.
   * @param string|null $description
   *   The repository description if any.
   * @param array $metaData
   *   Act like a sandbox to store arbitrary data.
   */
  public function __construct(
    public readonly string $vendor,
    public readonly string $name,
    public readonly string $defaultBranch,
    public readonly string $platformReference,
    public readonly ?string $description,
    public readonly array $metaData = [],
  ) {}

  /**
   * Get repository full-name.
   *
   * @return string
   *   Repository full name.
   */
  public function getFullName(): string {
    return $this->vendor . '/' . $this->name;
  }

}
