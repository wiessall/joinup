<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a release's title and version is unique within its solution.
 *
 * @Constraint(
 *   id = "UniqueReleaseTitleVersion",
 *   label = @Translation("Unique release title and version within solution", context = "Validation"),
 * )
 */
class UniqueReleaseTitleVersionConstraint extends Constraint {

  /**
   * The message to show when the constraint is not met.
   *
   * @var string
   */
  public $message = 'A release with title %title and version %version already exists in this solution. Please choose a different title or version.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\joinup_release\Plugin\Validation\Constraint\UniqueReleaseTitleVersionValidator';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return [];
  }

}
