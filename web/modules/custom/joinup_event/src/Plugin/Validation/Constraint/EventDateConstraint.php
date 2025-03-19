<?php

declare(strict_types=1);

namespace Drupal\joinup_event\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates the event duration for field_event_date.
 */
#[Constraint(
  id: 'EventDateConstraint',
  label: new TranslatableMarkup('Event duration fields constraint', [], ['context' => 'Validation']),
)]
class EventDateConstraint extends SymfonyConstraint {

  /**
   * The message to show if date range is longer than 2 weeks.
   *
   * @var string
   */
  public $message = 'The duration of the event cannot be longer than 2 weeks.';

}
