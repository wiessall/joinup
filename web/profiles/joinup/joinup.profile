<?php

/**
 * @file
 * Enables modules and site configuration for the Joinup profile.
 */

declare(strict_types=1);

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
