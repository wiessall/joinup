<?php

/**
 * @file
 * Field specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Html;
use Drupal\Core\Template\Attribute;
use Drupal\joinup_badge\BadgeService;

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_field_alter(array &$suggestions, array $variables): void {
  $element = $variables['element'];
  // Do not output field labels and wrapping markup for paragraph fields. This
  // excludes binding_requirement paragraphs which are not used like the other
  // paragraphs in the site (for content editing).
  if (
    isset($element['#entity_type'])
    && $element['#entity_type'] === 'paragraph'
    && $element['#bundle'] !== 'binding_requirement'
  ) {
    // Prepend the new suggestion right after the default field template. This
    // allows overriding single fields, while keeping all the rest markup-free.
    array_unshift($suggestions, 'field__bare');
  }
  // Add extra suggestion for field elements.
  if (isset($element['#extra_suggestion'])) {
    $suggestions[] = $element['#extra_suggestion'];
  }

  $base = implode('__', [
    'field',
    $element['#entity_type'],
    $element['#bundle'],
  ]);
  // Get the key of the $base suggestion. We insert the view mode specific
  // suggestion right after it.
  $baseKey = array_search($base, $suggestions, TRUE);
  if ($baseKey !== FALSE) {
    // Let's not assume that $suggestions is an indexed array...
    $baseKeyPos = array_search($baseKey, array_keys($suggestions), TRUE);
    $typeBundleModeSuggestion = implode('__', [
      'field',
      $element['#entity_type'],
      $element['#bundle'],
      $element['#view_mode'],
    ]);

    $suggestions = array_merge(
      array_slice($suggestions, 0, $baseKeyPos + 1),
      [$typeBundleModeSuggestion],
      array_slice($suggestions, $baseKeyPos + 1),
    );
  }

  // Also add suggestion which varies by entity, field, bundle and view mode.
  $suggestions[] = implode('__', [
    'field',
    $element['#entity_type'],
    $element['#field_name'],
    $element['#bundle'],
    $element['#view_mode'],
  ]);
}

/**
 * Implements hook_preprocess_field_ui_table().
 */
function ventuno_preprocess_field_ui_table(array &$variables): void {
  if ($variables['user']->isAuthenticated()) {
    $variables['#attached']['library'][] = 'ventuno/tabledrag';
  }
}

/**
 * Implements hook_preprocess_field_multiple_value_form().
 */
function ventuno_preprocess_field_multiple_value_form(array &$variables): void {
  if ($variables['user']->isAuthenticated()) {
    $variables['#attached']['library'][] = 'ventuno/tabledrag';
  }

  // Provide a 'for' property for field labels in multi-value fields
  // using id of the first element so that it is semantically clear
  // to which form element this label belongs.
  if ($variables['multiple']) {
    // Set new variable label, which contains title.
    $label = $variables['element']['#title'];
    $variables['label']['content'] = $label;
    // Lists of element types.
    $elements = [
      'target_id',
      'value',
      'uri',
    ];

    // Fixes link form element title.
    // @see ventuno_preprocess_form_element().
    if (isset($variables['element']['0']['uri'])) {
      foreach ($variables['table']['#rows'] as &$value_row) {
        $value_row['data'][1]['data']['uri']['#title'] = $label;
      }
    }

    // Set first input 'id' as 'for' attribute for table label.
    foreach ($elements as $value_element) {
      if (isset($variables['element']['0'][$value_element]['#id'])) {
        $variables['label']['attributes'] = new Attribute([
          'for' => $variables['element']['0'][$value_element]['#id'],
        ]);
        break;
      }
    }

    // Visually hide table header for specific fields.
    $fields = [
      // Node: event.
      'field_event_online_location',
      // Paragraph: binding_requirement.
      'expression',
      'affects',
      'has_participation',
      'relevant_report',
    ];
    if (\in_array($variables['element']['#field_name'], $fields)) {
      $variables['table']['#attributes']['class'][] = 'thead-visually-hidden';
    }
  }

  // Fix for inline_form_errors links, ensures every field (multiple or not)
  // has an ID and print it in the template.
  // See https://www.drupal.org/project/drupal/issues/3229493
  /* @see web/themes/ventuno/templates/form/field-multiple-value-form.html.twig */
  if (isset($variables['element']['#id'])) {
    $variables['attributes']['id'] = $variables['element']['#id'];
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_field__solution_last_update_date(&$variables): void {
  $variables['label'] = t('Last update');
}

/**
 * Implements hook_preprocess_field().
 */
function ventuno_preprocess_field(array &$variables): void {
  $element = $variables['element'];
  // View modes displayed as full page and field types with user input.
  if (in_array($element['#view_mode'], ['about', 'full'])
    && in_array($variables['field_type'], ['text_long', 'text_with_summary'])
  ) {
    // Add selectors.
    $variables['attributes']['class'][] = 'fieldset';
    $variables['attributes']['class'][] = 'fieldset--textarea';
  }
  // Make the topics appear as badges in solution headers.
  if ($element['#view_mode'] === 'group_header' && $element['#field_name'] === 'field_topic') {
    $variables['#attached']['library'][] = 'ventuno/badge';
    $items = $variables['items'];
    foreach ($items as $key => $item) {
      $badge_classes = ['badge', 'bg-secondary', 'rounded-pill'];
      $variables['items'][$key]['content']['#attributes'] = ['class' => $badge_classes];
    }
  }

  $field_names = ['eif_layer', 'eif_model', 'eif_principle'];
  if (in_array($element['#field_name'], $field_names)) {
    foreach ($variables['items'] as $key => $item) {
      $parts = explode(':', $item['content']['#title']);
      $title = empty($parts[1]) ? $item['content']['#title'] : trim($parts[1]);
      $variables['items'][$key]['content']['#title'] = $title;
    }
  }

  // @todo Remove this when working on https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-9873
  if (
    $variables['element']['#view_mode'] === 'confirmation' &&
    $variables['element']['#field_name'] === 'documentation'
  ) {
    $items = $variables['items'];
    foreach ($items as $key => $item) {
      $variables['items'][$key]['content']['#attributes'] += ['target' => '_blank'];
    }
  }

  // Render badges for the joinup_badges computed field.
  if ($element['#field_name'] === 'joinup_badges') {
    /** @var \Drupal\joinup_badge\BadgeServiceInterface $badgeService */
    $badgeService = \Drupal::service(BadgeService::class);
    foreach ($variables['items'] as $delta => &$item) {
      /** @var \Drupal\joinup_badge\BadgeFlagInterface $flagging */
      $flagging = $variables['element']['#items'][$delta]->entity;
      $item['content'] = [
        '#theme' => 'joinup_badge',
        '#icon_id' => $badgeService->getIcon($flagging->bundle()),
        '#label' => $flagging->getLabel(),
        '#description' => $badgeService->getDescription($flagging->bundle()),
      ];
    }
  }
}

/**
 * Implements hook_preprocess_field_group_accordion_item().
 */
function ventuno_preprocess_field_group_accordion_item(array &$variables): void {
  $element =& $variables['element'];
  // Remove numbers to ensure getting a valid id.
  $group_name = preg_replace('/[0-9]+/', '', $element['#group_name']);
  $id = substr($group_name, 6);
  // Provide unique ID for the accordion item.
  $variables['accordion_item_id'] = Html::getUniqueId($id);
}
