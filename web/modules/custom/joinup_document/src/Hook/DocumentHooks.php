<?php

declare(strict_types=1);

namespace Drupal\joinup_document\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\joinup_document\Entity\DocumentInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for the Joinup Document module.
 */
final class DocumentHooks {

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types): void {
    if (isset($entity_types['node'])) {
      $entity_types['node']->addConstraint('ReferencedSolutionConstraint');
    }
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for the node form.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    $node = $form_state->getFormObject()->getEntity();
    if ($node->bundle() !== 'document' || !isset($form['referenced_solution'])) {
      return;
    }

    $condition = [
      ':input[name="field_type"]' => ['value' => 'solution_use_cases'],
    ];
    $form['referenced_solution']['#states']['visible'] = $condition;
    $form['referenced_solution']['widget']['#states']['required'] = $condition;
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for node entities.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if (!$node instanceof DocumentInterface) {
      return;
    }

    if ($node->get('field_type')->value !== 'solution_use_cases' && !$node->get('referenced_solution')->isEmpty()) {
      $node->set('referenced_solution', []);
    }
  }

}
