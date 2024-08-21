<?php

/**
 * @file
 * Form specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_FORM_ID_alter().
 */
function ventuno_form_facets_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  $form['actions']['submit']['#attributes']['class'] = ['mt-3 w-100'];
  $form['actions']['reset']['#attributes']['class'][] = 'btn btn-outline-brand my-3 w-100';
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for form-element.html.twig.
 */
function ventuno_theme_suggestions_form_element_alter(array &$suggestions, array $variables): void {
  if (isset($variables['element']['#joinup_parent'])) {
    $suggestions[] = 'form_element__' . $variables['element']['#joinup_parent'];
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function ventuno_form_join_group_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  // Make the 'Join' modal link for anonymous users appear as a button.
  if (isset($form['authenticate'])) {
    $form['authenticate']['#attributes']['class'][] = 'btn';
    $form['authenticate']['#attributes']['class'][] = 'btn-brand';
  }

  if (isset($form['pending'])) {
    // Add classes for pending membership link.
    $form['pending']['#attributes']['class'][] = 'btn';
    $form['pending']['#attributes']['class'][] = 'btn-primary';
  }

  if (isset($form['leave'])) {
    // Add classes for leave membership link.
    $form['leave']['#confirm']['#attributes']['class'][] = 'btn';
    $form['leave']['#confirm']['#attributes']['class'][] = 'btn-secondary';
  }

  // Make the 'Join' button for logged-in users appear as button brand.
  if (isset($form['join'])) {
    $form['join']['#attributes']['class'][] = 'btn';
    $form['join']['#attributes']['class'][] = 'btn-brand';
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function ventuno_form_views_exposed_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  $route_name = \Drupal::routeMatch()->getRouteName();
  $view = $form_state->get('view');

  if ($view->id() === 'search') {
    $form['keys']['#theme'] = 'input__search_expose_form';
    $form['keys']['#title_display'] = 'invisible';
    $form['keys']['#attributes']['placeholder'] = t('Start typing to search…');
    $form['keys']['#type'] = 'textfield';
    $form['keys']['#joinup_parent'] = $form_id . '_' . $view->id();
    // Ensure the ID is unique, using "search-box" as base.
    $form['keys']['#id'] = Html::getUniqueId('search-box');
    $form['#id'] = Html::getUniqueId($form['#id']);
  }

  if ($view->id() === 'search_oss_catalogue') {
    $form['results_count'] = [
      '#type' => 'item',
      '#weight' => 0,
      '#markup' => '',
    ];
    $form['oss_keys']['#theme'] = 'input__search_expose_form';
    $form['oss_keys']['#title_display'] = 'invisible';
    $form['oss_keys']['#attributes']['placeholder'] = t('Start typing to search…');
    $form['oss_keys']['#joinup_parent'] = $form_id . '_' . $view->id();
  }

  if ($route_name != 'view.search.page_1' && $view->id() === 'search' || $route_name != 'view.search_oss_catalogue.search' && $view->id() === 'search_oss_catalogue') {
    // When re-using the exposed search form anywhere other than the search
    // page, hide the "Sort by" select.
    unset($form['sort_by']);
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_form_alter(array &$suggestions, array $variables): void {
  $id = $variables['element']['#id'];
  $suggestions[] = $id;
}

/**
 * Implements hook_preprocess_form().
 */
function ventuno_preprocess_form(array &$variables): void {
  $form_id = $variables['element']['#id'];

  switch ($form_id) {
    // Theme the form displayed in a modal when adding media through CKE5.
    case 'views-exposed-form-media-library-widget':
      $variables['attributes']['class'][] = 'my-0 row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3 align-items-end';
      break;
  }
}

/**
 * Implements hook_preprocess_form_element().
 */
function ventuno_preprocess_form_element(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/forms';
  if ($variables['type'] === 'radio' &&
    isset($variables['element']['#name']) &&
    $variables['element']['#name'] === 'layout_paragraphs[layout]') {
    $variables['attributes']['class'][] = 'm-0 p-0';
  }
}

/**
 * Implements hook_preprocess_form_element_label().
 */
function ventuno_preprocess_form_element_label(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/form-checkboxes';
  $variables['attributes']['class'][] = 'h4';
}

/**
 * Implements hook_form_alter().
 */
function ventuno_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  // Disable client side HTML5 validation @see https://html.spec.whatwg.org/multipage/form-control-infrastructure.html#attr-fs-novalidate
  $form['#attributes']['novalidate'] = 'novalidate';

  // Add classes to the delete action in any kind of form.
  if (isset($form['actions']['delete'])) {
    $classes = $form['actions']['delete']['#attributes']['class'] ?? [];
    $form['actions']['delete']['#attributes']['class'] = array_merge(
      $classes,
      ['btn', 'btn-outline-danger'],
    );
  }

  switch ($form_id) {
    case 'joinup_subscription_my_subscriptions':
      // Add classes for unsubscribe all link.
      if (isset($form['edit-actions']['unsubscribe_all'])) {
        $form['edit-actions']['#attributes']['class'] = [
          'd-flex',
          'justify-content-end',
        ];
        $form['edit-actions']['unsubscribe_all']['#attributes']['class'] = [
          'btn',
          'btn-brand',
        ];
      }
      break;

    case 'ogmenu_instance_quick_links_edit_form':
      $form['#attached']['library'][] = 'ventuno/tabledrag';
      break;
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_input_alter(array &$suggestions, array $variables): void {
  // Suggestion based on data twig suggestion created in form alter.
  if (isset($variables['element']['#extra_suggestion'])) {
    $suggestions[] = 'input__' . $variables['element']['#type'] . '__' . $variables['element']['#extra_suggestion'];
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_select_alter(array &$suggestions, array $variables): void {
  if (isset($variables['element']['#name'])) {
    $suggestions[] = 'select__' . $variables['element']['#name'];
  }
}

/**
 * Implements hook_preprocess_radios().
 */
function ventuno_preprocess_radios(array &$variables): void {
  if (isset($variables['element']['#name']) && $variables['element']['#name'] === 'layout_paragraphs[layout]') {
    $variables['attributes']['class'][] = 'd-flex col-gap-3';
  }
}

/**
 * Implements hook_form_BASE_FORM_ID_alter().
 */
function ventuno_form_poll_view_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  foreach (['result', 'cancel', 'back'] as $element) {
    if (!isset($form['actions'][$element])) {
      continue;
    }

    $form['actions'][$element]['#button_type'] = 'link';
    $form['actions'][$element]['#attributes']['class'][] = 'p-0';
  }

  if (isset($form['actions']['back'])) {
    $form['actions']['back']['#value'] = t('Hide results');
  }
}

/**
 * Implements hook_preprocess_input__submit().
 */
function ventuno_preprocess_input__submit(array &$variables): void {
  if (!isset($variables['element']['#button_type'])) {
    $variables['attributes']['class'][] = 'btn-primary';
  }
}

/**
 * Implements hook_preprocess_select().
 */
function ventuno_preprocess_select(array &$variables): void {
  if (isset($variables['element']['#name'])) {
    switch ($variables['element']['#name']) {
      case 'field_topic':
      case 'field_is_solution_type':
        $variables['attributes']['class'][] = 'h-50vh';
        break;
    }
  }
}
