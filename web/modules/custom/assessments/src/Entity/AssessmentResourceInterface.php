<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;

/**
 * Defines the assessment model interface.
 */
interface AssessmentResourceInterface extends RdfSyncEntityInterface {

  /**
   * Returns the resource property mappings used by the parser.
   *
   * Each entry key is the incoming API property name, and the value is an
   * associative array with the following keys:
   * - field_name: string, the Drupal field machine name to map to.
   * - is_required: bool, whether the property is required in the inbound API.
   * - is_reference: bool, whether the property represents a nested referenced
   *   resource that should be parsed recursively.
   *
   * @return array<string, array{field_name: string, is_required: bool, is_reference: bool}>
   *   The property mappings.
   */
  public function getResourcePropertyMappings(): array;

  /**
   * Returns a list of referenced entity properties.
   *
   * This will be used both to navigate down to the children elements and to
   * validate invalid keys.
   */
  public function getResourceReferenceProperties(): array;

  /**
   * Returns a list of allowed resource incoming API properties.
   *
   * @return array{string}
   *   An array of allowed resource properties.
   */
  public function getResourceAllowedProperties(): array;

  /**
   * Returns a list of required resource incoming API properties.
   *
   * @return array{string}
   *   An array of required resource properties.
   */
  public function getResourceRequiredProperties(): array;

  /**
   * Translates an inbound API value for a given mapped field.
   *
   * Implementations may convert raw inbound values to the expected Drupal
   * field value format. For now, the default behavior is to return the value
   * as-is.
   *
   * @param string $fieldName
   *   The Drupal field machine name resolved from the resource mapping.
   * @param mixed $values
   *   The inbound value(s) for the field.
   *
   * @return mixed
   *   The translated value(s) to be assigned to the field.
   */
  public function translateResourceValue(string $fieldName, mixed $values): mixed;

  /**
   * Method to be invoked before saving the entity to perform any adjustments.
   */
  public function resourceEntityPresave(): void;

}
