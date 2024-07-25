<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\joinup_solution\SolutionTitleDuplicateHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a field is unique for the given entity type within a bundle.
 *
 * This is the validator for the UniqueSolutionTitle.
 *
 * The following checks make sure that a solution must have a unique title among
 * within their collections.
 *
 * @see \Drupal\joinup_solution\Plugin\Validation\Constraint\UniqueSolutionTitle
 */
class UniqueSolutionTitleValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a new validator instance.
   *
   * @param \Drupal\joinup_solution\SolutionTitleDuplicateHelperInterface $solutionTitleHelper
   *   The solution title duplicate helper service.
   */
  public function __construct(protected SolutionTitleDuplicateHelperInterface $solutionTitleHelper) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('joinup_solution.title_duplicate_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($items, Constraint $constraint): void {
    if (!$item = $items->first()) {
      return;
    }

    /** @var \Drupal\rdf_entity\RdfInterface $solution */
    $solution = $items->getEntity();

    if ($solution->bundle() !== 'solution') {
      throw new \InvalidArgumentException("This validator is designated only for solutions.");
    }

    // Either the solution's title is not unique within affiliation, or the
    // solution title has duplicates with other solutions, all without
    // affiliation. In the first case ::titleIsUniqueWithinAffiliation() returns
    // FALSE, in the latter NULL.
    // @see \Drupal\joinup_solution\SolutionTitleDuplicateHelperInterface::titleIsUniqueWithinAffiliation()
    if (!$this->solutionTitleHelper->titleIsUniqueWithinAffiliation($solution)) {
      $this->context->addViolation($constraint->message, [
        '%value' => $item->value,
        '@entity_type' => $solution->getEntityType()->getSingularLabel(),
        '@field_name' => $items->getFieldDefinition()->getLabel(),
      ]);
    }
  }

}
