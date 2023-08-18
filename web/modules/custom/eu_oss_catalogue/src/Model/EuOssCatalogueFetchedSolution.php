<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Model;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;

/**
 * Data model for an OSS solution fetched from provider.
 */
class EuOssCatalogueFetchedSolution {

  /**
   * Creates a new fetched solution value object.
   *
   * @param string|null $url
   *   The OSS project URL.
   * @param string|null $name
   *   The OSS project name.
   * @param array $data
   *   Data containing OSS solution.
   * @param string|null $providerReference
   *   (optional) Some providers are assigning a proprietary ID to the solution.
   */
  public function __construct(
    public readonly ?string $url,
    public readonly ?string $name,
    public readonly array $data,
    public readonly ?string $providerReference = NULL,
  ) {}

  /**
   * Computes and returns a hash from data.
   *
   * @return string
   *   A hash from data.
   */
  public function getHash(): string {
    return Crypt::hashBase64(Json::encode($this->data));
  }

}
