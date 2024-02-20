<?php

declare(strict_types=1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Provides a validator for the CustomPageUnpublished constraint.
 *
 * @Constraint(
 *   id = "CustomPageUnpublishedConstraint",
 *   label = @Translation("Custom page published Constraint", context = "Validation"),
 *   type = "string"
 * )
 */
class CustomPageUnpublishedConstraint extends Constraint {

  /**
   * The violation message.
   *
   * @var string
   */
  public $message = "This page acts as landing page for the @group. In order to unpublish it should be first unset as landing page.";

}
