<?php

/**
 * @file
 * RDF Entity specific procedural functions.
 */

declare(strict_types = 1);

use Drupal\Core\Url;

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
}

/**
 * Implements hook_preprocess_rdf_entity().
 */
function ventuno_preprocess_rdf_entity__view_mode_tile(array &$variables, string $entity_type_id): void {
  $entity = $variables[$entity_type_id];
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();
  // Routes where tiles must be horizontal.
  $routes_horizontal_tiles = [
    'view.search.page_1'
  ];
  if (in_array($route_name, $routes_horizontal_tiles)) {
    $variables['horizontal'] = TRUE;
  }

  // Info (above the title).
  $variables['info']['icon'] = 'calendar-event';
  if ($changed_time = $entity->getChangedTime()) {
    $variables['info']['label'] = t('@label: @changed', [
      '@label' => t('Last update'),
      '@changed' => \Drupal::service('date.formatter')->format($changed_time, 'date_only'),
    ]);
  }

  switch ($entity->bundle()) {
    case 'asset_distribution':
      $variables['info']['icon'] = 'file-earmark-medical';
      $variables['info']['label'] = $variables['content']['field_ad_licence'];
      $variables['action'] = $variables['content']['field_ad_access_url'];
      break;

    case 'asset_release':
      $variables['label'] = $entity->label();
      $variables['highlighted']['text'] = 'v' . $entity->getVersion();
      $variables['info']['icon'] = 'calendar-event';
      if ($created_time = $entity->getCreatedTime()) {
        $variables['info']['label'] = t('@label: @created', [
          '@label' => t('Release date'),
          '@created' => \Drupal::service('date.formatter')->format($created_time, 'date_only'),
        ]);
      }
      $status = $variables['elements']['field_status'][0]['#plain_text'] ?? 'No status';
      switch ($status) {

        case 'Completed':
          $icon_id = 'check-circle';
          break;

        case 'Deprecated':
          $icon_id = 'file-x';
          break;

        case 'Under development':
          $icon_id = 'clock-history';
          break;

        case 'Withdrawn':
          $icon_id = 'patch-exclamation';
          break;
      }
      $variables['extra_data'][] = [
        'icon' => $icon_id ?? 'question-lg',
        'label' => $status,
      ];
      break;

    case 'collection':
      $variables['extra_data'][] = [
        'icon' => 'people',
        'label' => $variables['content']['members']['#count'],
      ];
      break;

    case 'solution':
      $variables['image'] = $variables['content']['field_is_logo'];
      // Add attributes that will allow JS to mark this tile as "Shared from".
      $rdf_entity = $variables['rdf_entity'];
      if (!$rdf_entity->get('collection')->isEmpty()) {
        /** @var \Drupal\rdf_entity\RdfInterface|null $parent */
        $parent = $rdf_entity->get('collection')->first()->entity;
        $variables['attributes']['data-joinup-parent-id'] = $parent->id();
        $variables['attributes']['data-joinup-parent-label'] = $parent->label();
      }
      // Add download counter if set.
      if (isset($variables['elements']['downloads']['count']['#access']) && $variables['elements']['downloads']['count']['#access'] === TRUE) {
        $variables['extra_data'][] = [
          'icon' => 'download',
          'label' => $variables['elements']['downloads']['count'],
        ];
      }
      break;

    case 'licence':
      // Licences do not store creation date.
      $variables['info'] = NULL;
      break;
  }
  _ventuno_preprocess_tile($variables, 'rdf_entity');
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
  if (isset($variables['content']['releases_overview'])) {
    $variables['content']['releases_overview']['#attributes'] = [
      'class' => [
        'btn',
        'btn-secondary',
      ],
    ];
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
  foreach ($eif_perspective->field_eif_solution_type as $solution_type) {
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
      'label' => 'Interoperability layer',
      'class' => 'interoperability-layer',
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
