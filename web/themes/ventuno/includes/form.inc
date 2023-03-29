<?php

/**
 * @file
 * Form specific procedural functions.
 */

declare(strict_types = 1);

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
    $form['keys']['#joinup_parent'] = $form_id . '_' . $view->id();
  }

  if ($route_name != 'view.search.page_1' && $view->id() === 'search') {
    // When re-using the exposed search form anywhere other than the search
    // page, hide the "Sort by" select.
    $form['sort_by']['#type'] = 'hidden';
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
 * Implements hook_preprocess_form_element().
 */
function ventuno_preprocess_form_element(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/forms';
}

/**
 * Implements hook_preprocess_form_element_label().
 */
function ventuno_preprocess_form_element_label(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/form-checkboxes';
}

/**
 * Implements hook_form_alter().
 */
function ventuno_form_alter(array &$form, FormStateInterface $form_state, string $form_id): void {
  // Add classes to the delete action in any kind of form.
  if (isset($form['actions']['delete'])) {
    $classes = $form['actions']['delete']['#attributes']['class'] ?? [];
    $form['actions']['delete']['#attributes']['class'] = array_merge(
      $classes,
      ['btn', 'btn-outline-danger'],
    );
  }
}
