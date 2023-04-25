<?php

/**
 * @file
 * User specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_user().
 */
function ventuno_preprocess_user(array &$variables): void {
  $variables['full_name'] = $variables['user']->getDisplayName();
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_user_alter(array &$suggestions, array $variables): void {
  // Add suggestion based on view mode for user entity.
  if (!empty($variables['elements']['#view_mode'])) {
    $original_theme_hook = $variables['theme_hook_original'];
    $view_mode = $variables['elements']['#view_mode'];
    $suggestions[] = $original_theme_hook . '__' . str_replace('-', '_', $view_mode);
  }
}

/**
 * Implements hook_preprocess_user__group_header().
 */
function ventuno_preprocess_user__group_header(array &$variables): void {
  // Create customizable 'member for' field.
  // It creates a timespan without label and with lower granularity.
  $variables['member_for'] = \Drupal::service('date.formatter')->formatTimeDiffSince($variables['user']->getCreatedTime(), ['granularity' => 1]);
  $variables['full_name'] = $variables['user']->getDisplayName();
}

/**
 * Implements hook_preprocess_search_api_field__user__field_user_content().
 */
function ventuno_preprocess_search_api_field__user__field_user_content(array &$variables): void {
  // Provides a nicer message when the user hasn't created any content.
  // The "no_results_found" render array is not there if there are results.
  if (empty($variables['no_results_found'])) {
    return;
  }

  $first_name = $variables['entity']->get('field_user_first_name')->value;
  if (!empty($first_name)) {
    $markup = t('@name does not have any content yet.', ['@name' => $first_name]);
  }
  else {
    $markup = t('This user does not have any content yet.');
  }
  $variables['no_results_found'] = [
    '#markup' => $markup,
  ];
}

/**
 * Implements hook_preprocess_user__inline().
 */
function ventuno_preprocess_user__inline(array &$variables): void {
  $variables['full_name'] = [
    '#theme' => 'username',
    '#account' => $variables['user'],
    '#attributes' => [
      'class' => [],
    ],
  ];
}

/**
 * Implements hook_preprocess_user().
 */
function ventuno_preprocess_user__view_mode_tile(&$variables) {
  // Truncate business title.
  $business_title = $variables['user']->get('field_user_business_title')->value;
  $variables['short_business_title'] = !empty($business_title) ? substr($business_title, 0, 30) : '';
}
