<?php

/**
 * @file
 * Enables modules and site configuration for the Joinup profile.
 */

declare(strict_types=1);

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function joinup_form_field_config_edit_form_alter(&$form): void {
  // Increase the maximum length of the file extension field to allow
  // registration of large amounts of extensions.
  if (isset($form['settings']['file_extensions']['#maxlength'])) {
    $form['settings']['file_extensions']['#maxlength'] = 1024;
  }
}

/**
 * Implements hook_entity_access().
 */
function joinup_entity_access(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
  // Moderators have the 'administer organic groups' permission so they can
  // manage all group content across all groups. However since the OG Menu
  // entities are also group content moderators are also granted access to the
  // OG Menu administration pages. Let's specifically deny access to these,
  // since we are handling the menu items transparently whenever custom pages
  // are created or deleted. Moderators and collection facilitators should only
  // have access to the edit form of an OG Menu instance so they can rearrange
  // the custom pages, but not to the entity forms of the menu items themselves.
  // In fact, nobody should have access to these pages except UID 1.
  if ($entity->getEntityTypeId() === 'ogmenu_instance' && $operation !== 'update') {
    return AccessResult::forbidden();
  }

  return AccessResult::neutral();
}

/**
 * Implements hook_field_widget_inline_entity_form_complex_form_alter().
 *
 * Simplifies the widget buttons when only a bundle is configured.
 */
function joinup_field_widget_inline_entity_form_complex_form_alter(&$element, FormStateInterface $form_state, $context): void {
  if (isset($element['actions']['bundle']['#type']) && $element['actions']['bundle']['#type'] == 'value') {
    $buttons = [
      'ief_add' => t('Add new'),
      'ief_add_existing' => t('Add existing'),
    ];

    foreach ($buttons as $key => $label) {
      if (!empty($element['actions'][$key])) {
        $element['actions'][$key]['#value'] = $label;
      }
    }
  }
}

/**
 * Implements hook_inline_entity_form_reference_form_alter().
 */
function joinup_inline_entity_form_reference_form_alter(&$reference_form, &$form_state): void {
  // Avoid showing two labels one after each other.
  $reference_form['entity_id']['#title_display'] = 'invisible';
}

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * - Disable access to the revision information vertical tab.
 *   This prevents access to the revision log and the revision checkbox too.
 * - Disable access to the comment settings. These are managed on collection
 *   level.
 * - Disable access to the meta information.
 * - Allow access to the uid field only to the moderators.
 */
function joinup_form_node_form_alter(&$form, FormStateInterface $form_state, $form_id): void {
  $form['revision_information']['#access'] = FALSE;
  $form['revision']['#access'] = FALSE;
  $form['meta']['#access'] = FALSE;

  if (isset($form['uid'])) {
    $form['uid']['#access'] = \Drupal::currentUser()->hasPermission('administer nodes');
  }

  foreach (['field_comments', 'field_replies'] as $field) {
    if (!empty($form[$field])) {
      $form[$field]['#access'] = FALSE;
    }
  }
}
