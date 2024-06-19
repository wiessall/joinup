<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_distribution\ParentDistributionResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a distribution's title is unique within the release.
 *
 * This is the validator for the UniqueAssetReleaseTitleConstraint.
 *
 * @see \Drupal\joinup_distribution\Plugin\Validation\Constraint\UniqueDistributionTitleConstraint
 */
class UniqueDistributionTitleValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The parent distribution resolver service.
   *
   * @var \Drupal\joinup_distribution\ParentDistributionResolver $parentDistributionResolver
   *   The parent distribution resolver service.
   */
  public function __construct(
    protected ParentDistributionResolver $parentDistributionResolver
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static (
      $container->get('joinup_distribution.parent_distribution_resolver')
    );
  }

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

    $distributions = $this->parentDistributionResolver->getParentsDistributions($rdf_entity->id());
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
