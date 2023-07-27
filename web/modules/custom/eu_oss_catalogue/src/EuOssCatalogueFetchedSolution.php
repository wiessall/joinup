<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

/**
 * Data model for an OSS solution fetched from provider.
 */
class EuOssCatalogueFetchedSolution {

  /**
   * Creates a new fetched solution value object.
   *
   * @param string $url
   *   The OSS project URL.
   * @param string $name
   *   The OSS project name.
   * @param array $data
   *   Data containing OSS solution.
   * @param string|null $providerAssignedId
   *   (optional) Some providers are assigning a proprietary ID to the solution.
   */
  public function __construct(
    public readonly string $url,
    public readonly string $name,
    public readonly array $data,
    public readonly ?string $providerAssignedId = NULL,
  ) {}

//  public function getHash(): string {
//
//  }
}
