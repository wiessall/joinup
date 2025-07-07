<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Checks if an entity field has a unique value within its bundle.
 *
 * The validation is case-insensitive. This constraint was created specifically
 * for the group entities "Short ID" field.
 */
#[Constraint(
  id: 'UniqueShortIdInsensitive',
  label: new TranslatableMarkup('Unique case insensitive field within a bundle constraint.', [], ['context' => 'Validation']),
)]
class UniqueShortIdInsensitiveConstraint extends SymfonyConstraint {

  /**
   * The regex to validate the format.
   *
   * @var string
   */
  public string $formatRegex = '/^[a-z0-9-]{4,26}$/';

  /**
   * Violation message when the format is invalid.
   *
   * @var string
   */
  public string $formatInvalidMessage = '@field_name must be 4–26 characters: only lowercase letters, digits, and hyphens allowed.';

  /**
   * Violation message when uniqueness fails.
   *
   * @var string
   */
  public string $notUniqueMessage = 'Content with @field_name %value already exists. Please choose a different @field_name.';

}
