<?php

declare(strict_types=1);

namespace Drupal\joinup\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeTypeInterface;

/**
 * Provides route responses for Joinup.
 */
class JoinupController extends ControllerBase {

  /**
   * Provides propose forms for node entities.
   *
   * This is used for the propose form of collections.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node bundle entity for which to generate the proposal form.
   *
   * @return array
   *   A render array for the proposal form.
   */
  public function proposeEntity(NodeTypeInterface $node_type): array {
    $node = $this->entityTypeManager()->getStorage('node')->create([
      'type' => $node_type->id(),
    ]);

    $form = $this->entityFormBuilder()->getForm($node, 'propose');
    $form['#title'] = $this->t('Propose @type', [
      '@type' => mb_strtolower($node_type->label()),
    ]);
    return $form;
  }

  /**
   * Handles access to the rdf_entity proposal form.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node bundle entity for which to generate the proposal form.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function createAccess(NodeTypeInterface $node_type): AccessResultInterface {
    if ($node_type->id() !== 'collection') {
      return AccessResult::forbidden();
    }
    return AccessResult::allowedIf($this->currentUser()->hasPermission("propose {$node_type->id()} node entity"));
  }

  /**
   * Provides a page outlining eligibility criteria for solutions.
   *
   * @return array
   *   The page as a render array.
   */
  public function eligibilityCriteria(): array {
    return ['#theme' => 'joinup_eligibility_criteria'];
  }

}
