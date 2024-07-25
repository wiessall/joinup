<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\rdf_entity\RdfInterface;

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
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection.
   *
   * @return array
   *   Return the form array to be rendered.
   *
   * @todo Change the parameter to a node in ISAICP-8574.
   */
  public function add(RdfInterface $rdf_entity): array {
    $solution = $this->createNewSolution($rdf_entity);

    // Pass the collection to the form state so that the parent connection is
    // established.
    // @see solution_add_form_parent_submit()
    return $this->entityFormBuilder()->getForm($solution, 'default', ['collection' => $rdf_entity->id()]);
  }

  /**
   * Handles access to the solution add form through collection pages.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection in which the solution is created.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createSolutionAccess(RdfInterface $rdf_entity): AccessResultInterface {
    // If the collection is archived, content creation is not allowed.
    if ($rdf_entity->getWorkflowState() === 'archived') {
      return AccessResult::forbidden();
    }

    // Users with 'administer organic groups' permission should have access
    // since this page can only be called from within a group.
    $user = $this->currentUser();
    if ($user->hasPermission('administer organic groups')) {
      return AccessResult::allowed();
    }

    if (!$rdf_entity->hasGroupPermission($user->id(), 'create solution content')) {
      return AccessResult::forbidden();
    }

    $solution = $this->createNewSolution($rdf_entity);
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
    return $this->entityTypeManager()->getStorage('node')->create([
      'type' => 'solution',
      'collection' => [$collection->id()],
    ]);
  }

}
