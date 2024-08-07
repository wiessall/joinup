<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_solution\Entity\Solution;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;
use Drupal\og\OgGroupAudienceHelper;

/**
 * Controller providing the form to add a new solution inside a collection.
 */
class SolutionController extends ControllerBase {

  /**
   * Controller for the base form.
   *
   * We need to override the functionality of the create form for pages
   * that include the rdf_entity id in the url so that the og audience field
   * is auto completed.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The collection.
   *
   * @return array
   *   Return the form array to be rendered.
   */
  public function add(NodeInterface $node): array {
    assert($node instanceof CollectionInterface);
    $solution = $this->createNewSolution($node);

    // Pass the collection to the form state so that the parent connection is
    // established.
    // @see solution_add_form_parent_submit()
    return $this->entityFormBuilder()->getForm($solution, 'default', [OgGroupAudienceHelper::DEFAULT_FIELD => $node->id()]);
  }

  /**
   * Handles access to the solution add form through collection pages.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The collection in which the solution is created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createSolutionAccess(NodeInterface $node): AccessResultInterface {
    assert($node instanceof CollectionInterface);
    // If the collection is archived, content creation is not allowed.
    if ($node->getWorkflowState() === 'archived') {
      return AccessResult::forbidden();
    }

    // Users with 'administer organic groups' permission should have access
    // since this page can only be called from within a group.
    $user = $this->currentUser();
    if ($user->hasPermission('administer organic groups')) {
      return AccessResult::allowed();
    }

    if (
      !$node instanceof GroupInterface ||
      !$node->hasGroupPermission($user->id(), 'create solution content')
    ) {
      return AccessResult::forbidden();
    }

    $solution = $this->createNewSolution($node);
    return AccessResult::allowedIf(!empty($solution->getWorkflow()->getAllowedTransitions($solution->getWorkflowState(), $solution)));
  }

  /**
   * Creates a new solution entity that is affiliated with the given collection.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $collection
   *   The collection to affiliate with the new solution.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface
   *   The unsaved solution entity.
   */
  protected function createNewSolution(CollectionInterface $collection): SolutionInterface {
    return Solution::create([
      'type' => 'solution',
      OgGroupAudienceHelper::DEFAULT_FIELD => $collection,
    ]);
  }

}
