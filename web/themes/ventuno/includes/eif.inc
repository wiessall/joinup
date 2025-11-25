<?php

/**
 * @file
 * EIF specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Url;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__solution__eif_perspective(array &$variables): void {
  /** @var \Drupal\joinup_solution\Entity\SolutionInterface $solution */
  $solution = $variables['node'];
  if (!$solution instanceof SolutionInterface) {
    return;
  }
  /** @var \Drupal\meta_entity\Entity\MetaEntityInterface $meta_entity */
  $meta_entity = $solution->eif_perspective->entity;
  if (!$meta_entity instanceof MetaEntityInterface) {
    return;
  }

  $eif_description = \Drupal::entityTypeManager()->getViewBuilder('meta_entity')->viewField($meta_entity->get('eif_description'));
  $variables['content']['eif_perspective_fields']['eif_description'] = $eif_description;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_eif_filter_list(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/listing-grid';
  $variables['#attached']['library'][] = 'ventuno/eif-filter';
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__solution__view_mode_search_list_item(array &$variables): void {
  /** @var \Drupal\joinup_licence\Entity\LicenceInterface $entity */
  $entity = $variables['elements']['#node'];
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

  $eif_perspective_view = \Drupal::entityTypeManager()->getViewBuilder('meta_entity')->viewField($eif_perspective->eif_description, [
    'label' => 'hidden',
  ]);
  $variables['eif_perspective_description'] = $eif_perspective_view;
  $variables['solution_url'] = $entity->toUrl()->setAbsolute()->toString();
  $variables['eif_perspective'] = Url::fromRoute('entity.node.eif_perspective', ['node' => $entity->id()])->toString();
}
