<?php

/**
 * @file
 * Utility procedural functions.
 */

declare(strict_types = 1);

use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_core\Controller\IdRedirect;
use Drupal\smart_trim\Truncate\TruncateHTML;
use Drupal\topic\Entity\TopicReferencingEntityInterface;

/**
 * Performs smart trimming on the provided string.
 *
 * @param string $string
 *   The string to trim.
 * @param int $length
 *   The maximum length to trim the string to.
 * @param bool $ellipsis
 *   Whether to append an ellipsis. Defaults to TRUE.
 *
 * @return string
 *   The trimmed string.
 */
function _ventuno_smart_trim(string $string, int $length, bool $ellipsis = TRUE): string {
  $truncate = new TruncateHTML();
  return $truncate->truncateChars($string, $length, $ellipsis ? '…' : '');
}

/**
 * Prepares some variables for tile view mode.
 *
 * @param array $variables
 *   The variables array.
 * @param string $entity_type_id
 *   The entity type ID.
 */
function _ventuno_preprocess_tile(array &$variables, string $entity_type_id): void {
  $entity = $variables[$entity_type_id];
  $bundle_key = \Drupal::entityTypeManager()->getDefinition($entity_type_id)->getKey('bundle');

  // Image.
  $variables['image'] = ($entity instanceof LogoInterface) && $entity->getLogoAsFile()
    ? $entity->getLogoAsRenderArray($variables['view_mode'])
    : [];

  // Topics.
  $topics = ($entity instanceof TopicReferencingEntityInterface) ? $entity->getTopics() : NULL;
  if ($topics) {
    $variables['badges'] = [];
    foreach ($topics as $topic) {
      $variables['badges'][]['label'] = $topic->label();
    }
    $num_of_items = count($topics);
    if ($num_of_items > 2) {
      $extra_items = $num_of_items - 2;
      $variables['badges'] = array_splice($variables['badges'], 0, 2);
      $word = ($extra_items < 2) ? t('topic') : t('topics');
      $variables['badges'][]['label'] = '+' . $extra_items . ' ' . $word;
    }
  }

  // Bundle label and icon.
  $bundle_type = $entity->get($bundle_key)->entity;
  $variables['bundle']['label'] = ucfirst($bundle_type->getSingularLabel() ?? $bundle_type->label());
  $variables['bundle']['icon'] = _ventuno_get_default_icon($entity->getEntityTypeId(), $entity->bundle());
}

/**
 * Returns the default icon for the given bundle or the generic entity icon.
 *
 * @param string $entity_type_id
 *   The entity type ID.
 * @param string $bundle
 *   The bundle.
 *
 * @return string
 *   The icon id.
 */
function _ventuno_get_default_icon(string $entity_type_id, string $bundle): string {
  $mapping = [
    'node' => [
      'custom_page' => 'file-earmark-richtext',
      'discussion' => 'chat-left-text',
      'document' => 'file-earmark-text',
      'event' => 'calendar-event',
      'news' => 'newspaper',
      'tallinn_report' => 'journal-text',
      'video' => 'file-earmark-play',
    ],
    'rdf_entity' => [
      'collection' => 'folder2-open',
      'licence' => 'file-earmark-medical',
      'asset_distribution' => 'file-earmark-post',
      'asset_release' => 'file-earmark-zip',
      'solution' => 'clipboard2-check',
    ],
  ];

  return $mapping[$entity_type_id][$bundle] ?? 'lightning-charge';
}

/**
 * Returns icon as render array for the bundle or a generic icon render array.
 *
 * @todo ISAICP-7709 With tiles redesign, the search items won't need this so
 * we can delete this function.
 *
 * @param string $entity_type_id
 *   The entity type ID.
 * @param string $bundle
 *   The bundle.
 *
 * @return array
 *   The icon class render array.
 */
function _ventuno_get_default_icon_render_array(string $entity_type_id, string $bundle): array {
  $mapping = [
    'node' => [
      'custom_page' => 'file-earmark-richtext',
      'discussion' => 'chat-left-text',
      'document' => 'file-earmark-text',
      'event' => 'calendar-event',
      'news' => 'newspaper',
      'tallinn_report' => 'journal-text',
      'video' => 'file-earmark-play',
    ],
    'rdf_entity' => [
      'collection' => 'folder2-open',
      'licence' => 'file-earmark-medical',
      'asset_distribution' => 'file-earmark-post',
      'asset_release' => 'file-earmark-zip',
      'solution' => 'clipboard2-check',
    ],
  ];

  $class = $mapping[$entity_type_id][$bundle] ?? 'lightning-charge';
  return [
    '#type' => 'inline_template',
    '#template' => '<svg class="bi img-fluid w-100 py-3 p-lg-3 text-white bg-secondary"><use xlink:href="' . base_path() . \Drupal::service('extension.list.theme')->getPath('ventuno') . '/assets/icons/icons.svg#{{ icon }}"></use></svg>',
    '#context' => [
      'icon' => $class,
    ],
  ];
}

/**
 * Builds the permalink for the given route.
 *
 * @return string|null
 *   The permalink URI.
 */
function _ventuno_build_permalink(): ?string {
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();

  $routes = [
    'entity.node.canonical',
    'entity.rdf_entity.canonical',
    'entity.rdf_entity.about_page',
  ];

  if (!in_array($route_name, $routes)) {
    return NULL;
  }

  if (!($parameters = $route_match->getRouteObject()->getOption('parameters')) || empty($parameters)) {
    return NULL;
  }

  foreach ($parameters as $key => $info) {
    if (!empty($info['type']) && $info['type'] == "entity:$key") {
      $type = $key;
      break;
    }
  }

  if (!isset($type) || !($entity = $route_match->getParameter($type))) {
    return NULL;
  }

  // Entity ID is pointing to the publication office redirect.
  $namespaces = implode('|', array_keys(
    IdRedirect::getEntityTypeFromPersistentUriNamespace()));
  if (preg_match('{^http://data.europa.eu/(?:' . $namespaces . ')/}', $entity->id())) {
    return $entity->id();
  }

  // Don't process the path to avoid pathauto aliasing.
  return $entity->toUrl('canonical', [
    'path_processing' => FALSE,
    'absolute' => TRUE,
  ])->toString();
}

/**
 * Provides a helper to create suggestions per view mode.
 *
 * @param array $suggestions
 *   Current suggestions, passed by reference.
 * @param array $variables
 *   Template variables.
 *
 * @see ventuno_theme_suggestions_eif_wrapper_alter()
 * @see ventuno_theme_suggestions_taxonomy_term_alter()
 */
function _ventuno_theme_suggestions_alter(array &$suggestions, array $variables): void {
  $original_theme_hook = $variables['theme_hook_original'];
  $view_mode = $variables['elements']['#view_mode'];
  $suggestions[] = $original_theme_hook . '__' . str_replace('-', '_', $view_mode);

  $entity_type_id = $variables['elements']['#entity_type'];
  $bundle = $variables['elements']["#{$entity_type_id}"]->bundle();
  $suggestions[] = $original_theme_hook . '__' . str_replace('-', '_', $bundle) . '__' . str_replace('-', '_', $view_mode);
}

/**
 * Gets author inline view mode as a render array.
 *
 * @param \Drupal\joinup_community_content\Entity\CommunityContentInterface $node
 *   The node, where we get the author.
 *
 * @return array
 *   Renderable array that contains the author.
 */
function _ventuno_get_author(CommunityContentInterface $node): array {
  /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('user');
  $author = $node->get('uid')->entity;
  return $view_builder->view($author, 'inline');
}
