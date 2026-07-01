<?php

declare(strict_types=1);

namespace Drupal\joinup_document\Plugin\Validation\Constraint;

use Drupal\joinup_document\Entity\DocumentInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ReferencedSolutionConstraint.
 */
class ReferencedSolutionConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint): void {
    if (!$entity instanceof DocumentInterface) {
      return;
    }

    if ($entity->get('field_type')->value !== 'solution_use_cases') {
      return;
    }

    if ($entity->get('field_referenced_solution')->isEmpty()) {
      $this->context->buildViolation($constraint->message)
        ->atPath('field_referenced_solution')
        ->addViolation();
    }
  }

}
