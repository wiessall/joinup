<?php

/**
 * @file
 * Menu specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Url;
use Drupal\block\Entity\Block;

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_menu_alter(array &$suggestions, array $variables): void {
  // Adds a theme suggestion for the menus rendered in the footer, so they can
  // be styled in the same way.
  if (in_array($variables['menu_name'], [
    'footer',
    'support',
    'footer-about-us',
    'footer-european-commission',
    'footer-follow-us',
    'footer-help-and-support',
  ], TRUE)) {
    $suggestions[] = 'menu__region_footer';
    $suggestions[] = 'menu__region_footer__' . str_replace('-', '_', $variables['menu_name']);
  }
  // Provide a separate suggestion for menus depending on their region.
  if (isset($variables['attributes']['data-joinup-block-id']) && $block = Block::load($variables['attributes']['data-joinup-block-id'])) {
    $region = $block->getRegion();
    $suggestions[] = $variables['theme_hook_original'] . '__region_' . $region;
    unset($variables['attributes']['data-joinup-block-id']);
  }
}

/**
 * Implements hook_preprocess_menu_local_action().
 */
function ventuno_preprocess_menu_local_action(array &$variables): void {
  $btn_classes = [
    'btn',
    'btn-md',
    'btn-secondary',
  ];
  $variables['link']['#options']['attributes']['class'] = $btn_classes;
}

/**
 * Implements hook_preprocess_links().
 */
function ventuno_preprocess_links(array &$variables): void {
  $variables['attributes']['class'][] = 'navigation-bottom__links';
  if (isset($variables['links'])) {
    // Mark this list item as having only the 'next' link.
    if (count($variables['links']) === 1) {
      $variables['attributes']['class'][] = 'navigation-bottom__links--only-next';
    }
    // Add 'next', 'prev', 'up' classes to bottom navigation list items.
    if (isset($variables['attributes']['data-joinup-block-id']) && $variables['attributes']['data-joinup-block-id'] === 'ventuno_toc_outline') {
      foreach ($variables['links'] as $key => $link) {
        if (in_array($key, ['next', 'prev', 'up'], TRUE)) {
          /** @var \Drupal\Core\Template\Attribute $attributes */
          $attributes = $link['attributes'];
          $attributes->addClass([$key]);
        }
      }
      // It makes no sense to keep this attribute.
      unset($variables['attributes']['data-joinup-block-id']);
    }
  }
}

/**
 * Implements hook_preprocess_menu().
 */
function ventuno_preprocess_menu__og__navigation(&$variables): void {
  $searchRoute = 'view.search_oss_catalogue.search';
  $currentRoute = \Drupal::routeMatch()->getRouteName();
  if ($currentRoute !== $searchRoute) {
    return;
  }

  foreach ($variables['items'] as $key => $item) {
    /** @var \Drupal\Core\Url $url */
    $url = $item['url'];
    if ($url->getRouteName() === $searchRoute) {
      $variables['items'][$key]['attributes']['class'][] = 'is-active';
    }
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_menu__og__quick_links(&$variables): void {
  if (!isset($variables['items']) || !is_array($variables['items'])) {
    return;
  }

  foreach ($variables['items'] as &$item) {
    $route = ($item['url'] instanceof Url) ? $item['url']->getRouteName() : NULL;
    $item['icon'] = match ($route) {
      // Release History.
      'joinup_release.solution_release.overview' => 'eye',
      // Website link.
      'joinup_menu.website_link' => 'link',
      // Custom page.
      'entity.node.canonical' => 'file-earmark-text',
      // EIF Perspective.
      'entity.rdf_entity.eif_perspective' => 'file-earmark-text',
      // Documentation link.
      'joinup_menu.documentation_file' => 'file-earmark-text',
      // Fallback.
      default => 'file-earmark-text',
    };
  }
}
