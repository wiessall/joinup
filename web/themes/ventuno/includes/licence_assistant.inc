<?php

/**
 * @file
 * Joinup Licencing Assistant specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_preprocess_licence_filter_list().
 */
function ventuno_preprocess_licence_filter_list(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/listing-grid';
  $variables['#attached']['library'][] = 'ventuno/licence-filter';
}

/**
 * Implements hook_preprocess_HOOK().
 *
 * Provides variables needed for the compatibility_check view mode of licences.
 * This is used on the Joinup Licence Assistant Compatibility Check overview
 * page.
 */
function ventuno_preprocess_rdf_entity__licence__compatibility_check(array &$variables): void {
  ventuno_preprocess_rdf_entity__licence__jla($variables);
}

/**
 * Implements hook_preprocess_HOOK().
 *
 * Provides variables needed for the both the JLA view modes (search_list_item
 * and compatibility_check) of licences.
 */
function ventuno_preprocess_rdf_entity__licence__jla(array &$variables): void {
  /** @var \Drupal\joinup_licence\Entity\LicenceInterface $entity */
  $entity = $variables['elements']['#rdf_entity'];

  $variables['content']['spdx_licence_id']['#label_display'] = 'hidden';
  $variables['content']['spdx_licence_external_url']['#attributes']['target'] = '_blank';
  $variables['content']['spdx_licence_external_url']['#attributes']['class'] = [
    'm-3', 'external',
  ];

  $variables['spdx_licence_id_plain'] = $entity->getSpdxLicenceId();
}

/**
 * Implements hook_preprocess_HOOK().
 *
 * Provides variables needed for the search_list_item view mode of licences.
 * This is used on the Joinup Licence Assistant overview page.
 */
function ventuno_preprocess_rdf_entity__licence__view_mode_search_list_item(array &$variables): void {
  ventuno_preprocess_rdf_entity__licence__jla($variables);

  /** @var \Drupal\joinup_licence\Entity\LicenceInterface $entity */
  $entity = $variables['elements']['#rdf_entity'];

  // Calculate variables related to the legal type.
  $types = $entity->getLegalTypes();
  if (!empty($types)) {
    $data_property_categories = [];
    $category_lists = [];
    foreach ($types as $type) {
      $parent = $type->getCategory();
      if (empty($parent)) {
        continue;
      }
      $parent_normalized_title = strtolower($parent->label());
      if (!isset($category_lists[$parent->id()])) {
        $category_lists[$parent->id()] = [
          'title' => $parent->label(),
          'weight' => $parent->getWeight(),
          'class' => $parent_normalized_title,
        ];
      }
      $category_lists[$parent->id()]['items'][] = [
        'title' => $type->label(),
        'weight' => $type->getWeight(),
        'description' => $type->getDescription(),
      ];
      $normalized_title = htmlentities(str_replace([' ', '/'], ['-', '-'], strtolower($type->label())));
      $data_property_categories[$type->id()] = $normalized_title;
    }

    if (!empty($data_property_categories)) {
      $sort_function = function ($a, $b) {
        return $a['weight'] <=> $b['weight'];
      };

      usort($category_lists, $sort_function);
      foreach ($category_lists as &$category_data) {
        usort($category_data['items'], $sort_function);
      }

      $variables['legal_categories'] = implode(' ', $data_property_categories);
      $variables['category_lists'] = $category_lists;
    }
  }
}
