<?php

/**
 * @file
 * Page title specific procedural functions.
 */

declare(strict_types=1);

use Drupal\joinup_community_content\Entity\CommunityContentInterface;

/**
 * Implements hook_preprocess_page_title().
 */
function ventuno_preprocess_page_title(array &$variables): void {
  // @todo Move the permalink computing outside the theme layer, in ISAICP-4724.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4724
  if ($permalink = _ventuno_build_permalink()) {
    $variables['permalink'] = $permalink;
  }

  $entity = $variables['entity'] ?? NULL;
  if ($entity instanceof CommunityContentInterface) {
    $variables['bundle'] = $entity->get('type')->entity->bundle();
    $variables['bundle_label'] = ucfirst($entity->get('type')->entity->getSingularLabel());

    $author_inline = _ventuno_get_author($entity);
    $variables['additional_content']['author'] = $author_inline;
  }

  if (\Drupal::routeMatch()->getRouteName() === 'entity.node.eif_perspective') {
    /** @var \Drupal\joinup_solution\Entity\SolutionInterface $solution */
    $solution = \Drupal::routeMatch()->getParameter('node');
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

  if ($route_name === "entity.node.canonical") {
    $entity = $route_match->getParameter('node');
    $suggestions[] = 'page_title__node__' . $entity->bundle();
    $variables['entity'] = $entity;
  }

  // Add Page Manager page specific libraries if we have any.
  $pageManagerPageId = $route_match->getRouteObject()?->getDefault('_page_manager_page');
  if ($pageManagerPageId) {
    $suggestions[] = implode('__', [
      $variables['theme_hook_original'],
      $pageManagerPageId,
    ]);
  }
}

/**
 * Implements hook_preprocess_page_title__node__news().
 */
function ventuno_preprocess_page_title__node__news(&$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['entity'];

  /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
  $field_news_headline = $view_builder->viewField($node->field_news_headline, 'default');
  $variables['additional_content']['field_news_headline'] = $field_news_headline;
}

/**
 * Implements hook_preprocess_page_title__node__event().
 */
function ventuno_preprocess_page_title__node__event(&$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['entity'];

  /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');

  // Get dates and location fields.
  $event_date = $field_event_date = $view_builder->viewField($node->get('field_event_date'), 'default');
  unset($event_date[0]['addtocal']);
  $variables['additional_content']['field_event_date'] = $event_date;
  $variables['additional_content']['field_add_to_cal'] = $field_event_date[0]['addtocal'];

  $field_event_location = $view_builder->viewField($node->get('field_location'), 'default');
  $variables['additional_content']['field_event_location'] = $field_event_location;
}

/**
 * Implements hook_preprocess_HOOK() for page-title--node--glossary.html.twig.
 */
function ventuno_preprocess_page_title__node__glossary(array &$variables): void {
  $variables['title'] = t('Glossary');
}

/**
 * Implements hook_preprocess_HOOK() for page-title--node--document.html.twig.
 */
function ventuno_preprocess_page_title__node__document(&$variables): void {
  /** @var \Drupal\joinup_document\Entity\DocumentInterface $node */
  $node = $variables['entity'];

  /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
  /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
  $date_formatter = \Drupal::service('date.formatter');
  $field_document_publication_date = $node->getPublicationTime();
  $variables['additional_content']['publication_date'] = empty($field_document_publication_date) ? '' : $date_formatter->format($field_document_publication_date, 'custom', 'd/m/Y');

  $field_short_title = $view_builder->viewField($node->field_short_title, 'default');
  $variables['additional_content']['field_short_title'] = $field_short_title;
}
