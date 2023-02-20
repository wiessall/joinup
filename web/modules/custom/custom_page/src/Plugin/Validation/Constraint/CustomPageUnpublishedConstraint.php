<?php

declare(strict_types = 1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
/**
 * Checks that custom page belong to group and is assign as landing page.
 *
 * @Constraint(
 *   id = "CustomPageUnpublishedConstraint",
 *   label = @Translation("Custom page published Constraint", context = "Validation"),
 *   type = "string"
 * )
 */
class CustomPageUnpublishedConstraint extends Constraint {
  /**
   * Constraint message.
   *
   * @var string
   */
  public $message = 'This page acts as landing page for the group %s. In order to delete/unpublish it should be first unset as landing page.';
}
