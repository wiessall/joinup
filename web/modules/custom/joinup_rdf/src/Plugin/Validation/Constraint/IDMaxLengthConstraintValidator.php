<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\Validation\Constraint;

use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the IDMaxLength constraint.
 */
class IDMaxLengthConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $entity, Constraint $constraint): void {
    if (!$entity instanceof EntityInterface || is_null($entity->id())) {
      return;
    }

    /** @var \Drupal\joinup_rdf\Plugin\Validation\Constraint\IDMaxLengthConstraint $constraint */
    if (mb_strlen($entity->id()) > $constraint->max) {
      $this->context->addViolation(
        $constraint->errorMessage,
        [
          '%id' => $entity->id(),
          '%max' => $constraint->max,
        ]
      );
    }
  }

}
