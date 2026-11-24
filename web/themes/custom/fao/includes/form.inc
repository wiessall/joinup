<?php

/**
 * hook_preprocess_element
 * Add class to captcha form login
 */
function fao_preprocess_input__textfield(&$variables) {
  $variables['attributes']['class'][] = 'form-control mt-0';

}

/**
 * Add theme suggestion for exposed form
 */
function fao_theme_suggestions_form_element_alter(array &$suggestions, array $variables)
{
  if (!empty($variables['element']) && !empty($variables['element']['#name'])) {

    $suggestions[] = 'form_element__' . $variables['element']['#type'];
    $suggestions[] = 'form_element__' . $variables['element']['#type']. '__'.str_replace('[', '_', str_replace(']', '', $variables['element']['#name']));

  }
}

/**
 * Add theme suggestion for fieldset
 */
function fao_theme_suggestions_fieldset_alter(array &$suggestions, array $variables)
{

  if(!empty($variables['element']) && !empty($variables['element']['#parents'])) {
    $suggestions[] = 'fieldset__' . $variables['element']['#parents'][0];
  }
}

/**
 * Add theme suggestion for details
 */
function fao_theme_suggestions_details_alter(array &$suggestions, array $variables)
{

  if(!empty($variables['element']) && !empty($variables['element']['#parents'])) {
    $suggestions[] = 'details__' . $variables['element']['#parents'][0];
  }
}

/**
 * Add theme suggestion for input
 */
function fao_theme_suggestions_input_alter(array &$suggestions, array $variables)
{

  if (!empty($variables['element'])) {

    if (!empty($variables['element']['#id'])) {
      $suggestions[] = $variables['theme_hook_original'] . '__' . str_replace('-', '_', $variables['element']['#id']);
    }

    // Add theme suggestion for search inputs based on the data-drupal-selector attribute
    // containing references to search words like "search_api_fulltext" or "search_api_views_fulltext".
    // This allows for custom theming of search-related input elements.
    if (isset($variables['element']['#attributes']['data-drupal-selector']) && str_contains($variables['element']['#attributes']['data-drupal-selector'], 'search')) {
      $suggestions[] = 'input__' . $variables['element']['#type'] . '__search';
    }

    // Add theme suggestion for reset buttons with search in their ID
    if (isset($variables['element']['#name']) && str_contains($variables['element']['#name'], 'reset')) {
      $suggestions[] = 'input__' . $variables['element']['#type'] . '__reset';
    }



  }

}

/**
 * Add theme suggestion for container
 */
function fao_theme_suggestions_container_alter(array &$suggestions, array $variables)
{
  if(!empty($variables['element']['#id'])) {

    $suggestions[] = 'container__'.str_replace('-','_',$variables['element']['#id']);
   }
}


/**
 * hook_suggestions_hook_alter
 */
function fao_theme_suggestions_form_element_label_alter(array &$suggestions, array $variables) {

  $label = str_replace(['--', '---'], '-', str_replace(['edit', '0'], '', $variables['element']['#id']));

  $suggestions[] = 'form_element_label_' .  strtr($label, '-', '_');

  return $suggestions;
}
