<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that id entity value is not too long.
 *
 * @Constraint(
 *   id = "IDMaxLength",
 *   label = @Translation("ID max length constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class IDMaxLengthConstraint extends Constraint {

  /**
   * Max length.
   *
   * @var int
   */
  public int $max = 697;

  /**
   * The error message that will be shown if the value is too long.
   *
   * @var string
   */
  public string $errorMessage = 'Entity id "%id" can not be longer than %max characters';

}
