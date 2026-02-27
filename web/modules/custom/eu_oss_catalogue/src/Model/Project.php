<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model;

use Drupal\Component\Utility\Crypt;

/**
 * Data model for a code platform project.
 */
class Project {

  /**
   * Silent errors.
   *
   * @var string[]
   */
  protected array $silentErrors = [];

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
   * Returns the publiccode.yml content, if present.
   *
   * @return string|null
   *   The raw publiccode.yml string, or NULL if not present.
   */
  public function getPubliccodeYml(): ?string {
    return $this->files['publiccodeYml'] ?? NULL;
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
    $data = get_object_vars($this);
    unset($data['silentErrors']);

    return $data;
  }

  /**
   * Add silent error.
   *
   * @param string $error
   *   An error.
   */
  public function addSilentError(string $error): void {
    $this->silentErrors[] = $error;
  }

  /**
   * Gets silent errors.
   *
   * @return array
   *   Errors.
   */
  public function getSilentErrors(): array {
    return $this->silentErrors;
  }

}
