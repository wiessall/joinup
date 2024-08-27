<?php

/**
 * @file
 * HTML, page, region specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Template\Attribute;
use Drupal\block\Entity\Block;
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\topic\Entity\TopicMetaEntityInterface;

/**
 * Implements hook_preprocess_page().
 */
function ventuno_preprocess_page(array &$variables): void {
  // Retrieve the Joinup version and link to display in the footer.
  // @todo Move this into a block.
  /** @var \Drupal\joinup_core\JoinupVersionInterface $joinup_version */
  $joinup_version = \Drupal::service('joinup_core.joinup_version');
  $variables['version'] = $joinup_version->getVersion();
  $variables['version_url'] = $joinup_version->getUrl();

  // Export the URL of the Interoperable Europe landing page.
  $landing_page = \Drupal::entityTypeManager()->getStorage('rdf_entity')->load(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID);
  if ($landing_page instanceof RdfInterface) {
    $variables['interoperable_europe_url'] = $landing_page->toUrl()->toString();
  }

  // Export the username to display in the header.
  $variables['user_name'] = \Drupal::currentUser()->getDisplayName();

  /** @var \Drupal\joinup_bookmark\JoinupBookmarkInterface $joinupBookmark */
  $joinupBookmark = \Drupal::service('joinup_bookmark.bookmark');
  $variables['bookmark_link'] = $joinupBookmark->getBookmarkLink();

  /** @var \Drupal\joinup_menu\MenuHelperInterface $menu_helper */
  $menu_helper = \Drupal::service('joinup_menu.menu_helper');
  $variables['landing_page_links'] = $menu_helper->getBclLandingPageLinks();

  // Export the user account menu in the format expected by the header template.
  $variables['profile_links'] = $menu_helper->getBclAccountMenu();

  // Populate the links shown to anonymous users.
  $variables['anonymous_links'] = $menu_helper->getBclAnonymousMenu();

  // The "About us" link is only shown if the Joinup collection exists.
  $variables['#cache']['tags'][] = 'joinup_collection:exists';
  // Moderator can change navbar links.
  $variables['#cache']['tags'][] = 'config:system.menu.navbar';

  $variables['hide_searchbar'] = FALSE;
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();
  if ($route_name == 'view.search.page_1') {
    $variables['hide_searchbar'] = TRUE;
  }

  $meta_entity = \Drupal::routeMatch()->getParameter('meta_entity');
  if ($route_name == 'entity.meta_entity.canonical' && isset($meta_entity) && $meta_entity instanceof TopicMetaEntityInterface) {
    $variables['meta_title'] = $meta_entity->label();
    $variables['meta_description']['#markup'] = $meta_entity->get('description')->value;
  }

  // Remove title on collection/solution canonical pages.
  if ($route_name === 'entity.rdf_entity.canonical' && $entity = $route_match->getParameter('rdf_entity')) {
    if (in_array($entity->getType(), ['collection', 'solution']) && $entity->getSetting('landing_page')->count() === 0) {
      unset($variables['page']['content']['ventuno_pagetitle']);
    }
  }

  $pageManagerPageId = $route_match->getRouteObject()?->getDefault('_page_manager_page');
  if ($pageManagerPageId) {
    // Indicate that the page is a page manager page.
    $variables['page_manager']['id'] = $pageManagerPageId;

    // Add Page Manager page specific libraries if we have any.
    if (array_key_exists("page_manager.$pageManagerPageId", \Drupal::service('library.discovery')->getLibrariesByExtension('ventuno'))) {
      $variables['#attached']['library'][] = "ventuno/page_manager.$pageManagerPageId";
    }
  }

  $block = Block::load('ventuno_privatemessagenotification');

  if ($block) {
    $block_content = \Drupal::entityTypeManager()
      ->getViewBuilder('block')
      ->view($block);

    $variables['private_message'] = $block_content;
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for container.html.twig.
 */
function ventuno_theme_suggestions_container_alter(array &$suggestions, array $variables): void {
  if (isset($variables['element']['#id'])) {
    $suggestions[] = 'container__' . str_replace('-', '_', $variables['element']['#id']);

    // Suggestion for form actions container if ID has a suffix.
    if (end($suggestions) !== 'container__edit_actions' && strpos($variables['element']['#id'], 'edit-actions') !== FALSE) {
      $suggestions[] = 'container__edit_actions';
    }
  }
  // Add extra suggestion for grid elements.
  $has_extra_suggestions = isset($variables['element']['#extra_suggestion']);
  if ($has_extra_suggestions) {
    $suggestions[] = $variables['element']['#extra_suggestion'];
  }
}

/**
 * Implements hook_preprocess_HOOK() for html.html.twig.
 */
function ventuno_preprocess_html(array &$variables): void {
  // Licence comparer specific styling.
  if (\Drupal::routeMatch()->getRouteName() === 'joinup_licence.comparer') {
    $variables['#attached']['library'][] = 'ventuno/licence-comparer';
  }
  // Toolbar library for logged-in users.
  if ($variables['user']->isAuthenticated()) {
    $variables['#attached']['library'][] = 'ventuno/toolbar';
  }
}

/**
 * Implements hook_preprocess_container__text_format_filter_help().
 */
function ventuno_preprocess_container__text_format_filter_help(array &$variables): void {
  $variables['attributes']['class'] = ['text-end', 'fs-sm'];
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for page.html.twig.
 */
function ventuno_theme_suggestions_page_alter(array &$suggestions, array $variables): void {
  if ($node = \Drupal::routeMatch()->getParameter('node')) {
    $content_type = $node->bundle();
    $suggestions[] = 'page__' . $content_type;
  }

  // Reuse search template for OSS catalogue search page.
  if (($view_id = \Drupal::routeMatch()->getParameter('view_id'))
    && $view_id === 'search_oss_catalogue') {
    $suggestions[] = 'page__search';
  }
}

/**
 * Implements hook_preprocess_container().
 */
function ventuno_preprocess_container(array &$variables): void {
  if (isset($variables['attributes']['data-drupal-facet-id'])) {
    $facet_id = $variables['attributes']['data-drupal-facet-id'];
    $valid_facet_ids = [
      'collection_type',
      'solution_content_bundle',
      'field_content_listing_type',
      'user_content_bundle',
      'content_listing_type',
      'content_bundle',
      'bookmark_entity_bundle',
    ];
    if (in_array($facet_id, $valid_facet_ids)) {
      $classes = ['tabs--content-type'];
      $variables['attributes']['class'] = $classes;
    }
  }
  if (isset($variables['attributes']['data-drupal-selector']) && $variables['attributes']['data-drupal-selector'] === 'edit-og-membership-bulk-form') {
    $variables['attributes']['class'] = 'row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3 align-items-end';
  }
  if (isset($variables['attributes']['data-drupal-selector']) && $variables['attributes']['data-drupal-selector'] === 'edit-actions') {
    // Target only the submit input of the collections members overview page.
    if (isset($variables['element']['submit']['#id']) && $variables['element']['submit']['#id'] === 'edit-submit-og-members-overview') {
      $variables['attributes']['class'] = 'd-flex flex-column d-md-block my-3';
    }
  }
}

/**
 * Implements hook_preprocess_container__facet_region__top().
 */
function ventuno_preprocess_container__facet_region__top(array &$variables): void {
  $classes = 'd-flex align-items-baseline border-bottom my-5 flex-wrap justify-content-between w-100';
  $variables['attributes']['class'][] = $classes;
}

/**
 * Implements hook_preprocess_layout__twocol_section().
 */
function ventuno_preprocess_layout__twocol_section(array &$variables): void {
  switch ($variables['settings']['column_widths']) {
    case '50-50':
      $first_col_class[] = 'col-md-6';
      $second_col_class[] = 'col-md-6';
      break;

    case '33-67':
      $first_col_class[] = 'col-md-4';
      $second_col_class[] = 'col-md-8';
      break;

    case '67-33':
      $first_col_class[] = 'col-md-8';
      $second_col_class[] = 'col-md-4';
      break;

    case '25-75':
      $first_col_class[] = 'col-md-3';
      $second_col_class[] = 'col-md-9';
      break;

    case '75-25':
      $first_col_class[] = 'col-md-9';
      $second_col_class[] = 'col-md-3';
      break;
  }

  $first_col_class[] = 'layout__region';
  $first_col_class[] = 'layout__region--first';
  $second_col_class[] = 'layout__region';
  $second_col_class[] = 'layout__region--second';
  $variables['first_col_class'] = $first_col_class;
  $variables['second_col_class'] = $second_col_class;
}

/**
 * Implements hook_preprocess_layout__threecol_section().
 */
function ventuno_preprocess_layout__threecol_section(array &$variables): void {
  switch ($variables['settings']['column_widths']) {
    case '25-50-25':
      $first_col_class[] = 'col-md-3';
      $second_col_class[] = 'col-md-6';
      $third_col_class[] = 'col-md-3';
      break;

    case '33-34-33':
      $first_col_class[] = 'col-md-4';
      $second_col_class[] = 'col-md-4';
      $third_col_class[] = 'col-md-4';
      break;

    case '25-25-50':
      $first_col_class[] = 'col-md-3';
      $second_col_class[] = 'col-md-3';
      $third_col_class[] = 'col-md-6';
      break;

    case '50-25-25':
      $first_col_class[] = 'col-md-6';
      $second_col_class[] = 'col-md-3';
      $third_col_class[] = 'col-md-3';
      break;
  }

  $first_col_class[] = 'layout__region';
  $first_col_class[] = 'layout__region--first';
  $second_col_class[] = 'layout__region';
  $second_col_class[] = 'layout__region--second';
  $third_col_class[] = 'layout__region';
  $third_col_class[] = 'layout__region--third';
  $variables['first_col_class'] = $first_col_class;
  $variables['second_col_class'] = $second_col_class;
  $variables['third_col_class'] = $third_col_class;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_layout(array &$variables): void {
  $base = 'l-ventuno--' . $variables['layout']->id();
  $bases[] = $base;
  if (!empty($variables['content']['#settings']['label'])) {
    $labelLower = mb_strtolower($variables['content']['#settings']['label']);
    $labelVariant = preg_replace('/[\W]/', '_', $labelLower);
    $labelVariant = preg_replace('/_+/', '-', $labelVariant);
    $bases[] = "$base--section-label-{$labelVariant}";
  }

  $variables['attributes']['class'] = array_unique(
    array_merge(
      $variables['attributes']['class'] ?? [],
      $bases,
    )
  );

  foreach ($variables['region_attributes'] as $region_name => $attribute) {
    assert($attribute instanceof Attribute);
    foreach ($bases as $baseClass) {
      $attribute->addClass("{$baseClass}__region-$region_name");
    }
  }
}
