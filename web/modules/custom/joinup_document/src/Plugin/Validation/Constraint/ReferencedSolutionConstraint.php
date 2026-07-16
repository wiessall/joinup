<?php

declare(strict_types=1);

namespace Drupal\joinup_document\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Requires at least one referenced solution for 'Solution use cases' documents.
 */
#[Constraint(
  id: 'ReferencedSolutionConstraint',
  label: new TranslatableMarkup('Referenced solution constraint', [], ['context' => 'Validation']),
)]
class ReferencedSolutionConstraint extends SymfonyConstraint {

  /**
   * The message to show if validation fails.
   *
   * @var string
   */
  public $message = 'A document of type Solution use cases must reference at least one solution.';

}
