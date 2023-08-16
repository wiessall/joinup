<?php

/**
 * @file
 * Paragraph specific procedural functions.
 */

declare(strict_types = 1);

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Implements hook_preprocess_paragraph().
 */
function ventuno_preprocess_paragraph(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/paragraph';
  $variables['#attached']['library'][] = 'ventuno/paragraph__' . $variables['paragraph']->bundle();
  $variables['attributes']['class'][] = 'paragraph--' . str_replace('_', '-', $variables['paragraph']->bundle());

  if (isset($variables['elements']['#paragraph_alignment'])) {
    $variables['attributes']['class'][] = 'd-flex';
    $variables['attributes']['class'][] = match ($variables['elements']['#paragraph_alignment']) {
      'center' => 'justify-content-center',
      'right' => 'justify-content-end',
      default => 'justify-content-start',
    };
  }
}

/**
 * Implements hook_paragraphs_behavior_options_HOOK().
 */
function ventuno_preprocess_field__paragraph__call_to_action(&$variables): void {
  $paragraph = $variables['element']['#object'];
  if ($paragraph instanceof ParagraphInterface) {
    $color = $paragraph->getBehaviorSetting('color', 'color', 'primary');
    $variables['attributes']['class'][] = 'btn';
    $variables['attributes']['class'][] = match ($color) {
      'brand' => 'btn-brand',
      'secondary' => 'btn-secondary',
      default => 'btn-primary',
    };
  }
}

/**
 * Implements hook_paragraphs_behavior_color_options_alter().
 */
function ventuno_paragraphs_behavior_color_options_alter(array &$options): void {
  $options['primary'] = t('Dark blue');
  $options['brand'] = t('Middle blue');
  $options['secondary'] = t('Light blue');
}

/**
 * Implements hook_preprocess_paragraph().
 */
function ventuno_preprocess_paragraph__accordion(array &$variables): void {
  $entity = $variables['paragraph'];
  $variables['attributes']['id'] = 'accordion' . $entity->id();
  $variables['attributes']['class'] = ['accordion', 'mb-4'];
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__configurable_listing(&$variables) {
  $paragraph = $variables['paragraph'];

  // For the configurable listing, the view mode is affected by the type of the
  // listing. Alter the view mode for the listing results based on the
  // 'list_style' field of the paragraph.
  $style = $paragraph->get('list_style')->value;
  $view_mode_mapping = [
    'grid' => 'view_mode_tile',
    'list' => 'view_mode_tile_horizontal',
  ];

  if ($results = &$variables['content']['content_listing']['#results']) {
    foreach ($results as &$result) {
      $result['#item']['#view_mode'] = $view_mode_mapping[$style];
      $cache_tags = CacheableMetadata::createFromRenderArray($result['#item']);
      $cache_tags->addCacheTags($paragraph->getCacheTags());
      $cache_tags->applyTo($result['#item']);
    }
  }

  $variables['style'] = $paragraph->get('list_style')->value;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__accordion_item(array &$variables): void {
  $entity = $variables['paragraph'];
  if ($entity->bundle() !== 'accordion_item') {
    return;
  }
  $parent = $entity->getParentEntity();
  if (!$parent || $parent->bundle() !== 'accordion') {
    return;
  }
  $variables['parent_id'] = $parent->id();
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_paragraph__poll(array &$variables): void {
  $variables['attributes']['class'][] = 'bg-light';
  $variables['attributes']['class'][] = 'p-3';
}
