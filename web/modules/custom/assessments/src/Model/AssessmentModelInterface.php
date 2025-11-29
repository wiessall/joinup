<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model interface.
 */
interface AssessmentModelInterface {

  /**
   * Returns the entity type ID.
   */
  public function getEntityTypeId(): string;

  /**
   * Returns the entity bundle.
   *
   * @param array $context
   *   Relevant context to pass to the bundle resolver.
   */
  public function getEntityBundle(array $context = []): string;

  /**
   * Returns the entity values.
   */
  public function getEntityValues(): array;

  /**
   * Returns the model path in the structure.
   *
   * @return string
   *   The model path.
   */
  public function getModelPath(): string;

  /**
   * Adds an entity value to the list of values of the model.
   *
   * @param string $key
   *   The key of the value.
   * @param int|string $value
   *   The value to add.
   */
  public function addEntityValue(string $key, int|string $value): void;

  /**
   * Returns a list of referenced entity properties.
   *
   * This will be used both to navigate down to the children elements and to
   * validate invalid keys.
   */
  public function getReferencedEntityProperties(): array;

  /**
   * Returns a list of allowed resource incoming API properties.
   *
   * @return array{string}
   *   An array of allowed resource properties.
   */
  public function getAllowedResourceProperties(): array;

  /**
   * Returns a list of required resource incoming API properties.
   *
   * @return array{string}
   *   An array of required resource properties.
   */
  public function getRequiredResourceProperties(): array;

  /**
   * Adds an error to the list of model errors, including the path.
   */
  public function addError(string $key, string $message): void;

}
