<?php

/**
 * @file
 * Enables modules and site configuration for the Joinup profile.
 */

declare(strict_types=1);

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
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
