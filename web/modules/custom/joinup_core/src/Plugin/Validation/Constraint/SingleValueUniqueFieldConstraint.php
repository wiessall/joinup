<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if an entity field has a unique value.
 *
 * In https://www.drupal.org/i/2478663 the `UniqueField` constraint has been
 * improved to allow validation of multi-value fields. However, this makes use
 * of an aggregated query which is not compatible with SPARQL storage entities.
 * This is a fork of `UniqueField` before https://www.drupal.org/i/2478663 was
 * committed.
 *
 * @see https://www.drupal.org/i/2478663
 *
 * @Constraint(
 *   id = "SingleValueUniqueField",
 *   label = @Translation("Unique field constraint (for single value fields)", context = "Validation"),
 * )
 */
class SingleValueUniqueFieldConstraint extends Constraint {

  /**
   * The violation message template.
   */
  public string $message = 'A @entity_type with @field_name %value already exists.';

}
