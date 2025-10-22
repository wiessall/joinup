<?php

declare(strict_types=1);

namespace Drupal\collection\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Provides the collection proposal form.
 *
 * Handles the propose form route for creating new collections. Collections
 * must be proposed rather than created directly to ensure proper moderation
 * workflow and validation.
 */
class CollectionProposeController extends ControllerBase {

  /**
   * Provides propose form for collection entities.
   *
   * @return array
   *   A render array for the proposal form.
   */
  public function proposeEntity(): array {
    $node = $this->entityTypeManager()->getStorage('node')->create([
      'type' => 'collection',
    ]);

    $form = $this->entityFormBuilder()->getForm($node, 'propose');
    $form['#title'] = $this->t('Propose collection');
    return $form;
  }

}
