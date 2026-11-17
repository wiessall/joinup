<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * An organisation cannot be its own parent, or the parent of one of its ancestors.
 */
#[Constraint(id: 'StiNoParentCycle', label: new TranslatableMarkup('No parent organisation cycle'))]
final class NoParentCycleConstraint extends SymfonyConstraint {

  public string $message = '%parent cannot be the parent organisation: it is this organisation or one of its sub-organisations.';

}
