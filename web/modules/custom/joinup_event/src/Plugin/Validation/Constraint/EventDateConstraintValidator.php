<?php

declare(strict_types=1);

namespace Drupal\joinup_event\Plugin\Validation\Constraint;

use Drupal\joinup_event\Entity\EventInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the duration fields.
 */
class EventDateConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    if (!$entity instanceof EventInterface) {
      return;
    }
    $dateRange = $entity->getDateRange();
    if (!is_string($dateRange['value']) || !is_string($dateRange['end_value'])) {
      return;
    }
    $startDate = strtotime($dateRange['value']);
    $endDate = strtotime($dateRange['end_value']);
    $diffDays = ($endDate - $startDate) / 86400;
    if ($diffDays > 14) {
      $this->context->buildViolation($constraint->message)
        ->atPath('field_event_date')
        ->addViolation();
    }
  }

}
