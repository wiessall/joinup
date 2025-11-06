<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Entity;

use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidationResultInterface;
use Drupal\node\NodeInterface;

/**
 * Interface of 'oss_solution' nodes.
 */
interface OssSolutionInterface extends NodeInterface {

  /**
   * Name of the field storing the raw publiccode.yml file content.
   *
   * @const string
   */
  public const string PUBLICCODE_YAML_FIELD = 'oss_publiccode_yml_content';

  /**
   * Appends a new provider to the source field.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   *
   * @return $this
   */
  public function addProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface;

  /**
   * Removes a provider from the list of sources.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   *
   * @return $this
   */
  public function removeProvider(EuOssCatalogueProviderInterface $provider): OssSolutionInterface;

  /**
   * Returns the language code of the richest translation.
   *
   * @return string
   *   The language code of the richest translation.
   */
  public function getRelevantLangCode(): string;

  /**
   * Checks whether the entity is archived.
   *
   * @return bool
   *   Whether this entity is archived.
   */
  public function isArchived(): bool;

  /**
   * Returns the repository URL.
   *
   * @return string
   *   The repository URL.
   */
  public function getUrl(): string;

  /**
   * Returns the publiccode yaml content.
   *
   * @return ?string
   *   The content of the first item in the pupliccode.yml content field, or
   *   NULL if the field is empty.
   */
  public function getPubliccodeYaml(): ?string;

  /**
   * Updates validation result of the OSS solution.
   *
   * @param \Drupal\eu_oss_catalogue\Validator\PublicCodeValidationResultInterface $validationResult
   *   A validation result object.
   *
   * @return $this
   */
  public function setValidationResult(PublicCodeValidationResultInterface $validationResult): self;

}
