<?php

declare(strict_types=1);

namespace Drupal\custom_page\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ThreeValuesConstraint constraint.
 */
class CustomPageUnpublishedConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    $entity = $value->getEntity();
    if (!$entity->isPublished()) {
      if (!custom_page_validate_landing_page($entity)) {
        $this->context->addViolation($constraint->message, ['%s' => $entity->getGroup()->label()]);
      }
    }
  }
}
