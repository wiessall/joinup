<?php

declare(strict_types=1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks that the alias does not belong to restricted list.
 *
 * @Constraint(
 *   id = "LandingPagePathFieldConstraint",
 *   label = @Translation(
 *    "Landing page path constraint",
 *    context = "Validation"
 *   ),
 *   type = "string"
 * )
 */
class LandingPagePathFieldConstraint extends Constraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public string $message = '@alias can not be used for URL alias field';

}
