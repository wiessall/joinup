<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model;

use Drupal\Component\Utility\Crypt;

/**
 * Data model for a code platform project.
 */
class Project {

  /**
   * Creates a new result object.
   *
   * @param string $namespace
   *   The namespace.
   * @param string $name
   *   The repository name.
   * @param string $url
   *   The repository URL.
   * @param string $platform
   *   The code hosting platform plugin ID.
   * @param string $defaultBranch
   *   The repository default branch.
   * @param string|null $latestReleaseTag
   *   The latest release tag.
   * @param string|null $latestReleaseDate
   *   The latest release date.
   * @param string|null $platformReference
   *   The code platform internal unique ID.
   * @param string|null $description
   *   The repository description, if any.
   * @param string|null $logo
   *   The URL of the project's logo/avatar.
   * @param int|null $numberOfForks
   *   Number of times the project has been forked.
   * @param int|null $numberOfStars
   *   Number of times the project has been starred.
   * @param int|null $numberOfIssues
   *   Number of open issues for the project.
   * @param int|null $numberOfPullRequests
   *   Number of open pull requests.
   * @param string[] $files
   *   A list of file blobs keyed by a file identifier.
   */
  public function __construct(
    public readonly string $namespace,
    public readonly string $name,
    public readonly string $url,
    public readonly string $platform,
    public readonly string $defaultBranch,
    public readonly ?string $latestReleaseTag = NULL,
    public readonly ?string $latestReleaseDate = NULL,
    public readonly ?string $platformReference = NULL,
    public readonly ?string $description = NULL,
    public readonly ?string $logo = NULL,
    public readonly ?int $numberOfForks = NULL,
    public readonly ?int $numberOfStars = NULL,
    public readonly ?int $numberOfIssues = NULL,
    public readonly ?int $numberOfPullRequests = NULL,
    public readonly array $files = [],
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

  /**
   * Returns the project platform base URL.
   *
   * @return string
   *   The project platform base URL.
   */
  public function getHostingPlatformUrl(): string {
    return preg_replace("@/{$this->getFullName()}$@", '', $this->url);
  }

  /**
   * Computes and returns a hash from metadata.
   *
   * @return string
   *   A hash from metadata.
   */
  public function getHash(): string {
    return Crypt::hashBase64(serialize($this->toArray()));
  }

  /**
   * Returns an array representation of the value object.
   *
   * @return array
   *   Array representation of the value object.
   */
  public function toArray(): array {
    $array = [];
    foreach (get_object_vars($this) as $property => $value) {
      $array[$property] = $value;
    }
    return $array;
  }

}
