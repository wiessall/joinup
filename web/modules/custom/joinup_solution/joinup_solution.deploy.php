<?php

/**
 * @file
 * Deploy functions for the Joinup Solution module.
 */

declare(strict_types=1);

/**
 * Update the submit_your_interoperability_sol webform elements and access rules.
 */
function joinup_solution_deploy_201500(): void {
  $webform_storage = \Drupal::entityTypeManager()->getStorage('webform');
  $webform = $webform_storage->load('submit_your_interoperability_sol');

  if ($webform === NULL) {
    return;
  }

  $elements = $webform->getElementsDecoded();

  $solution_element = [
    '#type' => 'entity_autocomplete',
    '#title' => 'Solution',
    '#prepopulate' => TRUE,
    '#required' => TRUE,
    '#target_type' => 'node',
    '#selection_handler' => 'default:node',
    '#selection_settings' => [
      'target_bundles' => [
        'solution' => 'solution',
      ],
    ],
  ];

  // Prepend solution as the first field.
  $elements = ['solution' => $solution_element] + $elements;

  $webform->setElements($elements);

  // Restrict form access to authenticated users only.
  $access = $webform->getAccessRules();
  $access['create'] = [
    'roles' => ['authenticated'],
    'users' => [],
    'permissions' => [],
  ];
  $webform->setAccessRules($access);

  $webform->save();
}
