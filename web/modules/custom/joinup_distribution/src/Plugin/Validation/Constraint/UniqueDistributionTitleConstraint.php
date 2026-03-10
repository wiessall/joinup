<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if a distribution's title is unique within its release.
 */
#[Constraint(
  id: 'UniqueDistributionTitle',
  label: new TranslatableMarkup('Unique distribution title within release', [], ['context' => 'Validation']),
)]
class UniqueDistributionTitleConstraint extends SymfonyConstraint {

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
  public function getRequiredOptions(): array {
    return [];
  }

}
