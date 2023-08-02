<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;

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
   * @param string|null $providerReference
   *   (optional) Some providers are assigning a proprietary ID to the solution.
   */
  public function __construct(
    protected string $url,
    protected string $name,
    protected array $data,
    protected ?string $providerReference = NULL,
  ) {}

  /**
   * Returns the solution URL.
   *
   * @return string
   *   The solution URL.
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Sets the solution URL.
   *
   * @param string $url
   *   Solution URL.
   *
   * @return $this
   */
  public function setUrl(string $url): self {
    $this->url = $url;
    return $this;
  }

  /**
   * Returns the solution name.
   *
   * @return string
   *   The solution name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Sets the solution name.
   *
   * @param string $name
   *   Solution name.
   *
   * @return $this
   */
  public function setName(string $name): self {
    $this->name = $name;
    return $this;
  }

  /**
   * Returns the solution data.
   *
   * @return array
   *   The solution data.
   */
  public function getData(): array {
    return $this->data;
  }

  /**
   * Sets the solution data.
   *
   * @param array $data
   *   Solution data.
   *
   * @return $this
   */
  public function setData(array $data): self {
    $this->data = $data;
    return $this;
  }

  /**
   * Returns the solution provider reference.
   *
   * @return string|null
   *   The solution provider reference.
   */
  public function getProviderReference(): ?string {
    return $this->providerReference;
  }

  /**
   * Sets the solution provider reference.
   *
   * @param string|null $providerReference
   *   Solution provider reference.
   *
   * @return $this
   */
  public function setProviderReference(?string $providerReference): self {
    $this->providerReference = $providerReference;
    return $this;
  }

  /**
   * Computes and returns a hash from data.
   *
   * @return string
   *   A hash from data.
   */
  public function getHash(): string {
    return Crypt::hashBase64(Json::encode($this->getData()));
  }

}
