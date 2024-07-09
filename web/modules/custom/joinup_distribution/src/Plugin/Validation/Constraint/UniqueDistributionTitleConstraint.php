<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a distribution's title is unique within its release.
 *
 * @Constraint(
 *   id = "UniqueDistributionTitle",
 *   label = @Translation("Unique distribution title within release", context = "Validation"),
 * )
 */
class UniqueDistributionTitleConstraint extends Constraint {

  /**
   * The message to show when the constraint is not met.
   *
   * @var string
   */
  public $message = 'A distribution with title %title already exists in this %bundle. Please choose a different title.';

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return UniqueDistributionTitleValidator::class;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return [];
  }

}
