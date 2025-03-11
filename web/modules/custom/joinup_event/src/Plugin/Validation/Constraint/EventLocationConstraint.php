<?php

declare(strict_types=1);

namespace Drupal\joinup_event\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates the event location field group.
 */
#[Constraint(
  id: 'EventLocationConstraint',
  label: new TranslatableMarkup('Event location fields constraint', [], ['context' => 'Validation']),
)]
class EventLocationConstraint extends SymfonyConstraint {

  /**
   * The message to show if validation fails.
   *
   * @var string
   */
  public $message = 'At least one location field should be filled in.';

}
