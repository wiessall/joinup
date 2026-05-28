<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Validation\Constraint;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Transition;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the CollectionArchivePublishedSolutions constraint.
 */
class CollectionArchivePublishedSolutionsValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof CollectionInterface) {
      return;
    }

    $archiveState = $value->getWorkflow()
      ->getTransition(Transition::Archive->value)
      ->getToState()
      ->getId();

    if ($value->getWorkflowState() !== $archiveState) {
      return;
    }

    // Skip if the entity was already archived before this save.
    if (!$value->isNew() && $value->original instanceof CollectionInterface && $value->original->getWorkflowState() === $archiveState) {
      return;
    }

    if ($value->getSolutions(TRUE, TRUE)) {
      $this->context->addViolation($constraint->message);
    }
  }

}