<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a distribution's title is unique within the release.
 *
 * @see \Drupal\joinup_distribution\Plugin\Validation\Constraint\UniqueDistributionTitleConstraint
 */
class UniqueDistributionTitleValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The parent distribution resolver service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
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

    if (!($parent = $entity->getParent())) {
      return;
    }

    $distributions = $parent->getDistributions();
    foreach ($distributions as $distribution) {
      if ($distribution->label() === $entity->label() && $distribution->id() !== $entity->id()) {
        // @todo Remove this in ISAICP-8572 and move the ::get('type') directly
        //   to the if condition.
        $bundle = ($parent instanceof ReleaseInterface) ? strtolower($parent->get('type')->entity->label()) : $parent->get('rid')->entity->label();
        $this->context->addViolation($constraint->message, [
          '%title' => $entity->label(),
          '%bundle' => strtolower($bundle),
        ]);

        return;
      }
    }
  }

}
