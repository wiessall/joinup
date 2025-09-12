<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

/**
 * Remove display custom attribute from general_criteria_for_interoperab.
 */
function joinup_webform_post_update_200700(): void {
  $storage = \Drupal::entityTypeManager()->getStorage('webform');

  if ($webform = $storage->load('general_criteria_for_interoperab')) {
    $elements = $webform->getElementsDecoded();
    if (isset($elements['openness']['specify_the_license_used']['#format_attributes']['visibility'])) {
      unset($elements['openness']['specify_the_license_used']['#format_attributes']['visibility']);
      $webform->setElements($elements);
      $webform->save();
      \Drupal::logger('joinup_webform')->notice('Removed display custom attribute from general_criteria_for_interoperab.');
    }
  }
}
