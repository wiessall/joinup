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
 * Implements hook_preprocess_HOOK() for page-title--node--glossary.html.twig.
 */
function ventuno_preprocess_page_title__node__glossary(array &$variables): void {
  $variables['title'] = t('Glossary');
}
