<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_solution\Form\JoinSolutionForm;

/**
 * Defines a service for #lazy_builder callbacks for the Collection module.
 */
readonly class SolutionLazyBuilders implements TrustedCallbackInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FormBuilderInterface $formBuilder,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Lazy builder callback; builds the Join Collection form.
   *
   * @param string $solution_id
   *   The solution ID.
   *
   * @return array
   *   A renderable array containing the comment form.
   */
  public function renderJoinSolutionForm(string $solution_id): array {
    $solution = $this->entityTypeManager->getStorage('node')->load($solution_id);
    return $this->formBuilder->getForm(JoinSolutionForm::class, $this->currentUser, $solution);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['renderJoinSolutionForm'];
  }

}
