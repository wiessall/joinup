<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for node entities.
 */
final class JoinupCoreNodeHooks {

  /**
   * Implements hook_form_node_form_alter().
   *
   * Trims the title before entity constraint validation runs, ensuring
   * uniqueness checks compare against the normalised value.
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['title']['widget'][0]['value'])) {
      $form['title']['widget'][0]['value']['#element_validate'][] =
        [
          self::class,
          'trimTitleElement',
        ];
    }
  }

  /**
   * Trims leading/trailing whitespace from an element value.
   */
  public static function trimTitleElement(array &$element, FormStateInterface $form_state): void {
    $form_state->setValueForElement($element, trim((string) $element['#value']));
  }

  /**
   * Implements hook_node_presave().
   *
   * Ensures the title is trimmed for all save paths, including API.
   */
  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->getTitle() !== NULL) {
      $node->setTitle(trim($node->getTitle()));
    }
  }

}
