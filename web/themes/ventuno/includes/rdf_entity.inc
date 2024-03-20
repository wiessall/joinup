<?php

/**
 * @file
 * RDF Entity specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Url;
use Drupal\joinup_workflow\ArchivableEntityInterface;

/**
 * Implements hook_preprocess_rdf_entity().
 */
function ventuno_preprocess_rdf_entity(array &$variables): void {
  // Workaround for incorrect rendering of RDF entity label.
  // @see https://www.drupal.org/project/rdf_entity/issues/3209500
  $variables['label'] = $variables['rdf_entity']->label();

  // Provide variable to know whether the page has been set as the landing page
  // of a group, so we can add a <h1> title in the group header.
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();
  if ($route_name === 'entity.rdf_entity.canonical' && $entity = $route_match->getParameter('rdf_entity')) {
    if (in_array($entity->getType(), ['collection', 'solution'])) {
      $variables['is_canonical'] = TRUE;
    }
  }

  // View modes where tiles are expected.
  $tile_view_modes = ['view_mode_tile', 'view_mode_tile_horizontal', 'carousel'];
  // Prepare data for the tiles.
  if (in_array($variables['view_mode'], $tile_view_modes)) {
    _ventuno_preprocess_tile($variables, 'rdf_entity');
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_rdf_entity__explore_item(array &$variables): void {
  /** @var \Drupal\rdf_entity\RdfInterface $entity */
  $entity = $variables['rdf_entity'];
  // Trim the labels of the items in the Explore block on the homepage down to a
  // manageable size.
  $variables['label'] = _ventuno_smart_trim($entity->label(), 45);
}

/**
 * Implements hook_preprocess_HOOK() for rdf_entity__solution__group-header.
 */
function ventuno_preprocess_rdf_entity__solution__group_header(array &$variables): void {
  $entity = $variables['rdf_entity'];
  if (isset($variables['content']['releases_overview'])) {
    $variables['content']['releases_overview']['#attributes'] = [
      'class' => [
        'btn',
        'btn-secondary',
      ],
    ];
  }
  if ($entity instanceof ArchivableEntityInterface) {
    $variables['is_archived'] = $entity->isArchived();
  }
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_rdf_entity__solution__view_mode_search_list_item(array &$variables): void {
  /** @var \Drupal\joinup_licence\Entity\LicenceInterface $entity */
  $entity = $variables['elements']['#rdf_entity'];
  $eif_perspective = $entity->eif_perspective->entity;
  if (empty($eif_perspective)) {
    return;
  }

  // Provides variables needed for the search_list_item view mode of licences.
  // This is used on the Joinup Licence Assistant overview page.
  $data_property_categories = [];

  $parent_id = 'solution-category';
  $category_lists[$parent_id] = [
    'title' => 'Solution category',
    'weight' => 3,
    'class' => $parent_id,
  ];
  $category_lists[$parent_id]['items'] = [];
  foreach ($eif_perspective->eif_solution_type as $solution_type) {
    $solution_type = $solution_type->entity;
    $category_lists[$parent_id]['items'][] = [
      'title' => $solution_type->label(),
      'weight' => 0,
      'description' => '',
    ];

    $normalized_title = htmlentities(str_replace([' ', '/'], ['-', '-'], strtolower($solution_type->label())));
    $data_property_categories[$solution_type->id()] = $normalized_title;
  }

  $data = [
    'principle' => [
      'field' => 'eif_principle',
      'label' => 'Underlying principle',
      'class' => 'underlying-principles',
      'weight' => 0,
    ],
    'layer' => [
      'field' => 'eif_layer',
      'label' => 'Interoperability layers',
      'class' => 'interoperability-layers',
      'weight' => 1,
    ],
    'model' => [
      'field' => 'eif_model',
      'label' => 'Conceptual model',
      'class' => 'conceptual-model',
      'weight' => 2,
    ],
  ];

  $search = [' ', '/', ':'];
  $replace = ['-', '-', ''];
  foreach ($data as $key => $key_data) {
    $terms = $eif_perspective->{$key_data['field']}->referencedEntities();
    if ($terms) {
      $category_lists[$key] = [
        'title' => $key_data['label'],
        'weight' => $key_data['weight'],
        'class' => $key_data['class'],
      ];
      $category_lists[$key]['items'] = [];
      foreach ($terms as $term) {
        // @todo Use an alternative title instead of dynamically splitting it.
        // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7110.
        $parts = explode(':', $term->label());
        $name = empty($parts[1]) ? $term->label() : trim($parts[1]);

        $category_lists[$key]['items'][] = [
          'title' => $name,
          'weight' => $term->getWeight(),
          'description' => $term->description->value,
        ];

        $normalized_title = htmlentities(str_replace($search, $replace, strtolower($term->label())));
        $data_property_categories[$term->id()] = $normalized_title;
      }
    }
  }

  $variables['attributes']['data-joinup-eif-category'] = implode(' ', $data_property_categories);
  $variables['attributes']['class'][] = 'eif-tile';

  if (!empty($data_property_categories)) {
    $sort_function = function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    };

    usort($category_lists, $sort_function);
    foreach ($category_lists as &$category_data) {
      usort($category_data['items'], $sort_function);
    }

    $variables['eif_categories'] = implode(' ', $data_property_categories);
    $variables['category_lists'] = $category_lists;
  }

  $eif_perspective_view = \Drupal::entityTypeManager()->getViewBuilder('rdf_meta_entity')->viewField($eif_perspective->field_eif_description, [
    'label' => 'hidden',
  ]);
  $variables['eif_perspective_description'] = $eif_perspective_view;
  $variables['solution_url'] = $entity->toUrl()->setAbsolute()->toString();
  $variables['eif_perspective'] = Url::fromRoute('entity.rdf_entity.eif_perspective', ['rdf_entity' => $entity->id()])->toString();
}

/**
 * Implements hook_preprocess_page_title__rdf_entity__asset_distribution().
 */
function ventuno_preprocess_page_title__rdf_entity__asset_distribution(array &$variables): void {
  /** @var \Drupal\rdf_entity\Entity\Rdf $entity */
  $entity = $variables['entity'];

  // Get the view builder service from the entity type manager.
  /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('rdf_entity');
  // Get the file size and licence fields.
  $field_ad_file_size = $entity->field_ad_file_size;
  $field_ad_licence = $entity->field_ad_licence;
  $field_ad_file_size = $view_builder->viewField($field_ad_file_size, 'default');
  $field_ad_licence = $view_builder->viewField($field_ad_licence, 'default');

  $variables['additional_content']['field_ad_file_size'] = $field_ad_file_size;
  $variables['additional_content']['field_ad_licence'] = $field_ad_licence;
}
