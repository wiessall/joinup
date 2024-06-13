<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\Validation\Constraint;

use Drupal\joinup_distribution\Entity\DistributionInterface;
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
    if (!$entity instanceof DistributionInterface) {
      return;
    }

    /** @var \Drupal\rdf_entity\RdfInterface $rdf_entity */
    if (!$rdf_entity = $entity->distribution_parent->entity) {
      return;
    }

    $distributions = \Drupal::getContainer()->get('joinup_distribution.parent_distribution_resolver')->getParentsDistributions($rdf_entity->id());
    foreach ($distributions as $distribution) {
      if ($distribution->label() === $entity->label() && $distribution->id() !== $entity->id()) {
        $this->context->addViolation($constraint->message, [
          '%title' => $entity->label(),
          '%bundle' => strtolower($rdf_entity->get('rid')->entity->label()),
        ]);

        return;
      }
    }
  }

}
