<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sti_owner_review\ParentChain;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates StiNoParentCycle on field_parent_organization, for edits made in the owner form.
 */
final class NoParentCycleConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  public static function create(ContainerInterface $container): self {
    return new self($container->get('entity_type.manager'));
  }

  /**
   * @param \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items
   * @param \Drupal\sti_owner_review\Plugin\Validation\Constraint\NoParentCycleConstraint $constraint
   */
  public function validate(mixed $items, Constraint $constraint): void {
    $entity = $items->getEntity();
    if ($entity->isNew()) {
      return;
    }
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    foreach ($items as $item) {
      if ($item->target_id !== NULL && ParentChain::wouldCycle($storage, (string) $entity->id(), (string) $item->target_id)) {
        $this->context->addViolation($constraint->message, ['%parent' => $item->entity?->label() ?? $item->target_id]);
      }
    }
  }

}
