<?php
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\render\Markup;

/**
 * hook_suggestions_hook_alter
 */
function fao_theme_suggestions_user_alter(array &$suggestions, array $variables) {

  /** @var \Drupal\taxonomy\TermInterface $term */
  $user = $variables['elements']['#user'];

  $suggestions[] = 'user__' .  strtr($variables['elements']['#view_mode'], '.', '_');

  return $suggestions;
}

/**
 * hook_form_FORM_ID_alter
 *
 * Alter password reset form to change submit label
 */
function fao_form_user_pass_alter(&$form, FormStateInterface $form_state) {

  $form['actions']['submit']['#attributes']['class'][] = 'text-uppercase btn btn-primary button';
  $form['actions']['submit']['#value'] = t('Send new password');

  $form['name']['#attributes']['placeholder'] = $form['name']['#title'];
  $form['name']['#attributes']['class'][] = 'form-control';

  $form['mail']['#prefix'] = '<p class="small">';

}

/**
 * hook_preprocess_block__hook
 *
 * Alter the login block
 */
function fao_preprocess_block__user_login_block(&$variables) {
  $login_form = &$variables['content']['user_login_form'];

  //move reset password link inside form
  $login_form['request_password'] = $variables['content']['user_links']['#items']['request_password'];
  unset($variables['content']['user_links']);
  //change request new password link title
  $login_form['request_password']['#title'] = Markup::create(t('Lost password?').'<i class="bi bi-chevron-right"></i>');
  $login_form['request_password']['#attributes']['class'][] = 'mt-1 mb-3 text-uppercase';

  //hide labels for username and password and add placeholders
  $login_form['name']['#title_display'] = 'after';
  $login_form['name']['#attributes']['placeholder'] = $login_form['name']['#title'];
  $login_form['pass']['#title_display'] = 'after';
  $login_form['pass']['#attributes']['placeholder'] = $variables['content']['user_login_form']['pass']['#title'];

  //change submit button label
  $login_form['actions']['#attributes']['class'][] = 'text-align-right';
  $login_form['actions']['submit']['#value'] = t('Sign in');
  $login_form['actions']['submit']['#attributes']['class'][] = 'text-uppercase';
  //swap submit button and request password link
  $login_form['request_password']['#weight'] = 10;
  $login_form['actions']['submit']['#weight'] = 20;
}

/**
 * hook_form_user_login_form_alter__hook
 *
 * Alter the login form
 */
function fao_form_user_login_form_alter(&$form, &$form_state, $form_id) {
  $form['name']['#title_display'] = 'after';
  $form['name']['#description'] = '';
  $form['name']['#attributes']['placeholder'] = $form['name']['#title'];
  $form['name']['#attributes']['class'][] = 'form-control mb-1';
  $form['pass']['#title_display'] = 'after';
  $form['pass']['#description'] = '';
  $form['pass']['#attributes']['placeholder'] = $form['pass']['#title'];
  $form['pass']['#attributes']['class'][] = 'form-control mb-1';

  $form['actions']['submit']['#attributes']['class'][] = 'btn btn-primary button mt-3';
}
