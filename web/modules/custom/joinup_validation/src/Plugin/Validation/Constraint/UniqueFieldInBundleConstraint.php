<?php

declare(strict_types=1);

namespace Drupal\joinup_validation\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if an entity field has a unique value (unique within it's bundle).
 *
 * This is specifically useful for base field, as you can limit the constraint
 * to certain bundles.
 */
#[Constraint(
  id: 'UniqueFieldInBundle',
  label: new TranslatableMarkup('Unique field within a bundle constraint', [], ['context' => 'Validation']),
)]
class UniqueFieldInBundleConstraint extends SymfonyConstraint {

  /**
   * The message to show when validation fails.
   *
   * @var string
   */
  public $message = 'Content with @field_name %value already exists. Please choose a different @field_name.';

  /**
   * The bundles for which this constraint applies.
   *
   * @var string
   */
  public $bundles;

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return '\Drupal\joinup_validation\Plugin\Validation\Constraint\UniqueFieldValueInBundleValidator';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): ?string {
    return 'bundles';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['bundles'];
  }

}
