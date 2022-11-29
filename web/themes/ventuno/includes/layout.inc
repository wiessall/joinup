<?php

/**
 * @file
 * HTML, page, region specific procedural functions.
 */

declare(strict_types = 1);

use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\rdf_entity\RdfInterface;

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

  // Show links to 'Interoperable Europe' and 'Interoperability Solutions'.
  /** @var \Drupal\joinup_menu\MenuHelperInterface $menu_helper */
  $menu_helper = \Drupal::service('joinup_menu.menu_helper');
  $variables['landing_page_links'] = $menu_helper->getBclLandingPageLinks();

  // Export the user account menu in the format expected by the header template.
  $variables['profile_links'] = $menu_helper->getBclAccountMenu();

  // Populate the links shown to anonymous users.
  $variables['anonymous_links'] = $menu_helper->getBclAnonymousMenu();

  // The "About us" link is only shown if the Joinup collection exists.
  $variables['#cache']['tags'][] = 'joinup_collection:exists';

  $variables['hide_searchbar'] = FALSE;
  $route_name = \Drupal::routeMatch()->getRouteName();
  if ($route_name == 'view.search.page_1') {
    $variables['hide_searchbar'] = TRUE;
  }
}

/**
 * Implements hook_preprocess_HOOK().
 *
 * Exposes the About section which is defined as a variant of the homepage in
 * Page Manager as a variable. This section is using a Layout but needs to be
 * rendered outside the main content region which is controlled by Page Manager.
 */
function ventuno_preprocess_page__home(array &$variables): void {
  /** @var \Drupal\page_manager\Entity\PageVariant $about_section_variant */
  $about_section_variant = \Drupal::entityTypeManager()->getStorage('page_variant')->load('homepage-layout_builder-1');
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('page_variant');
  $variables['about'] = $view_builder->view($about_section_variant);
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
 * Implements hook_preprocess_container().
 */
function ventuno_preprocess_container(array &$variables): void {
  if (isset($variables['attributes']['data-drupal-facet-id'])) {
    $facet_id = $variables['attributes']['data-drupal-facet-id'];
    $valid_facet_ids = [
      'collection_type',
      'solution_content_bundle',
    ];
    if (in_array($facet_id, $valid_facet_ids)) {
      $classes = [
        'tabs--content-type',
        'g-2',
        'my-4',
        'justify-content-center',
        'row',
        'row-cols-2',
        'row-cols-md-4',
        'row-cols-xl-6',
        'row-cols-xxl-8',
      ];
      $variables['attributes']['class'] = $classes;
    }
  }
}
