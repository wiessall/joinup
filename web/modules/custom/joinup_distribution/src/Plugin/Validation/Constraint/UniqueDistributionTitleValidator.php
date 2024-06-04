<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\Validation\Constraint;

use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_distribution\Entity\DistributionsParentInterface;
use Drupal\solution\Entity\SolutionInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a distribution's title is unique within the release.
 *
 * This is the validator for the UniqueAssetReleaseTitleConstraint.
 *
 * @see \Drupal\joinup_distribution\Plugin\Validation\Constraint\UniqueDistributionTitleConstraint
 */
class UniqueDistributionTitleValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    if (!$items->first()) {
      return;
    }

    $entity = $items->getEntity();
    assert($entity instanceof DistributionInterface);

    if (!($parent = $entity->parent->entity) || !$parent instanceof DistributionsParentInterface) {
      return;
    }
    $fieldName = $parent instanceof SolutionInterface ? 'field_is_distribution' : 'field_isr_distribution';
    /** @var \Drupal\joinup_distribution\Entity\DistributionInterface[] $distributions */
    $distributions = $parent->get($fieldName)->referencedEntities();
    foreach ($distributions as $distribution) {
      if ($distribution->label() === $entity->label() && $distribution->id() !== $entity->id()) {
        $this->context->addViolation($constraint->message, [
          '%title' => $entity->label(),
          '%bundle' => strtolower($parent->get('rid')->entity->label()),
        ]);

        return;
      }
    }
  }

}
