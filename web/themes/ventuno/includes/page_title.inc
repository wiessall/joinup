<?php

/**
 * @file
 * Page title specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_page_title().
 */
function ventuno_preprocess_page_title(array &$variables): void {
  // @todo Move the permalink computing outside the theme layer, in ISAICP-4724.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4724
  if ($permalink = _ventuno_build_permalink()) {
    $variables['permalink'] = $permalink;
  }
  if (\Drupal::routeMatch()->getRouteName() === 'entity.rdf_entity.eif_perspective') {
    /** @var \Drupal\solution\Entity\SolutionInterface $solution */
    $solution = \Drupal::routeMatch()->getParameter('rdf_entity');
    $variables['title'] = t('EIF Perspective attributes of @title', ['@title' => $solution->label()]);
  }
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for page-title.html.twig.
 *
 * Adds suggestions when the route is the canonical view of a rdf entity or a
 * node.
 */
function ventuno_theme_suggestions_page_title_alter(array &$suggestions, array &$variables): void {
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();

  // @todo ISAICP-7491 Make the bundle and label available so type is shown.
  // Pasted from joinup_theme.theme.
  // if ($entity instanceof ContentEntityInterface) {
  //   /** @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info */
  //   $bundle_info = \Drupal::service('entity_type.bundle.info');
  //   $info = $bundle_info->getBundleInfo($entity->getEntityTypeId());
  //   $bundle = $entity->bundle();
  //   $variables['bundle'] = $bundle;
  //   $variables['bundle_label'] = $info[$bundle]['label'] ?? $bundle;
  // }

  $entity_types = ['rdf_entity', 'node'];
  foreach ($entity_types as $entity_type) {
    if ($route_name === "entity.{$entity_type}.canonical") {
      /** @var \Drupal\core\Entity\EntityInterface $entity */
      $entity = $route_match->getParameter($entity_type);
      $suggestions[] = 'page_title__' . $entity_type . '__' . $entity->bundle();
      $variables['entity'] = $entity;
    }
  }
}

/**
 * Implements hook_preprocess_page_title__node__news().
 */
function ventuno_preprocess_page_title__node__news(&$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['entity'];

  /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
  $field_news_headline = $view_builder->viewField($node->field_news_headline, 'default');
  $variables['additional_content']['field_news_headline'] = $field_news_headline;

  $published = \Drupal::service('date.formatter')->format($node->published_at->published_at_or_created, 'date_only');
  $variables['additional_content']['published'] = $published;

  $update_date = \Drupal::service('date.formatter')->format($node->getChangedTime(), 'custom', 'd/m/Y');
  $variables['additional_content']['updated'] = $update_date;

  // @todo ISAICP-7491 Pass the author to the template
  // $author_inline = _ventuno_get_author($node);
  // $variables['additional_content']['author'] = $author_inline;
}

/**
 * Implements hook_preprocess_HOOK() for page-title--node--glossary.html.twig.
 */
function ventuno_preprocess_page_title__node__glossary(array &$variables): void {
  $variables['title'] = t('Glossary');
}
