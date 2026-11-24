<?php

/**
 * Implements hook_theme_suggestions_HOOK().
 *
 * Add views field template suggestions.
 *
 * @inheritdoc
 */
function fao_theme_suggestions_views_view_field_alter(array &$suggestions, array $variables) {

  $suggestions[] = 'views_view_field__' . $variables['field']->field;
}
function fao_theme_suggestions_views_view_unformatted_alter(array &$suggestions, array $variables) {
  $suggestions[] = 'views_view_unformatted__' . $variables['view']->id().'__'.$variables['view']->current_display;
}

/**
 * Implements hook_form_FORM_ID_alter() for views_exposed_form.
 *
 * Injects the vocabulary machine name into the hidden filter so the view
 * can apply vocabulary-dependent conditional logic without client-side JS.
 */
function fao_form_views_exposed_form_alter(array &$form, \Drupal\Core\Form\FormStateInterface $form_state): void {
  $view = $form_state->get('view');
  if (!$view || $view->id() !== 'taxonomy_term_index') {
    return;
  }

  $tid = $view->args[0] ?? NULL;
  if (!$tid) {
    return;
  }

  $term = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->load($tid);

  if ($term && isset($form['field_vocabulary_machine_names'])) {
    $form['field_vocabulary_machine_names']['#default_value'] = $term->bundle();
    $form['field_vocabulary_machine_names']['#access']        = FALSE;
  }
}

/**
 * Implements hook_preprocess_views_view().
 */
function fao_preprocess_views_view(&$variables) {
  $view = $variables['view'];
  $exposed_input = $view->getExposedInput();
  $active_filters = array_filter($exposed_input, function($value) {
    return $value !== '' && $value !== 'All' && !is_null($value);
  });

  $variables['has_filters_applied'] = !empty($active_filters);

  // Add your taxonomy term logic
  $view = $variables['view'];
  if ($view->id() == 'taxonomy_term_index') {
    $tid = $view->args[0] ?? NULL;
    if ($tid) {
      $term = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->load($tid);
      if ($term) {
        $variables['term'] = $term;
        $variables['vocabulary'] = $term->bundle();
      }
    }
  }
    // Add content type variable from exposed filter. Usage:
    $type = $view->getExposedInput()['type'] ?? NULL;
    $variables['content_type'] = is_array($type) ? reset($type) : $type;
}
