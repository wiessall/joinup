<?php

/**
 * @file
 * User specific procedural functions.
 */

declare(strict_types=1);

use Drupal\taxonomy\TermInterface;

/**
 * Implements hook_preprocess_user().
 */
function ventuno_preprocess_user(array &$variables): void {
  $variables['full_name'] = $variables['user']->getDisplayName();

  // View modes where tiles are expected.
  $tile_view_modes = [
    'view_mode_tile',
    'view_mode_tile_horizontal',
  ];
  // Prepare data for the tiles.
  if (in_array($variables['view_mode'], $tile_view_modes)) {
    $user = $variables['elements']['#user'];
    // Text.
    $nationality = $business_title = NULL;
    if (!$user->get('field_user_business_title')->isEmpty()) {
      $business_title = $user->get('field_user_business_title')->value;
    }
    if (!$user->get('field_user_nationality')->isEmpty()) {
      $nationality_entity = $user->get('field_user_nationality')->entity;
      if ($nationality_entity instanceof TermInterface) {
        $nationality = t('Country of origin: @country', ['@country' => $nationality_entity->label()]);
      }
    }

    if ($business_title && $nationality) {
      $variables['text'] = $business_title . '. ' . $nationality;
    }
    elseif ($business_title) {
      $variables['text'] = $business_title;
    }
    elseif ($nationality) {
      $variables['text'] = $nationality;
    }

    // Badges.
    if (!$user->get('field_user_professional_domain')->isEmpty()) {
      $domains = $user->get('field_user_professional_domain');
      $variables['badges'] = [];
      $max_domains = 2;
      if (is_iterable($domains)) {
        foreach ($domains as $domain) {
          $domain_term = $domain->entity;
          $variables['badges'][]['label'] = $domain_term instanceof TermInterface ? $domain_term->label() : NULL;
        }
        $num_of_items = count($domains);
        // Display the actual domain instead of "+ 1 domain".
        if ($num_of_items > $max_domains + 1) {
          $extra_items = $num_of_items - $max_domains;
          $variables['badges'] = array_splice($variables['badges'], 0, $max_domains);
          $variables['badges'][] = [
            'label' => '+' . $extra_items . ' ' . t('domains'),
            'overflow' => TRUE,
          ];
        }
      }
    }
  }
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
 * Implements hook_preprocess_username().
 */
function ventuno_preprocess_username(array &$variables): void {
  // Variable name_full is set in joinup_user.module to make the full name
  // visible on hover.
  $variables['attributes']['title'] = $variables['name_full'];
}
