<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a release's title and version are unique within the solution.
 *
 * This is the validator for the UniqueReleaseTitleVersionConstraint.
 *
 * The following checks make sure that a release must have a unique combination
 * of title and version within the solution.
 *
 * @see \Drupal\joinup_release\Plugin\Validation\Constraint\UniqueReleaseTitleVersionConstraint
 */
class UniqueReleaseTitleVersionValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$value->first()) {
      return;
    }

    /** @var \Drupal\joinup_release\Entity\ReleaseInterface $entity */
    $entity = $value->getEntity();
    if (!joinup_release_title_version_is_unique($entity)) {
      $this->context->addViolation($constraint->message, [
        '%title' => $entity->label(),
        '%version' => $entity->getVersion(),
      ]);
    }
  }

}
