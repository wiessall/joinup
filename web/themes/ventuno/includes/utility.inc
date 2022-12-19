<?php

/**
 * @file
 * Utility procedural functions.
 */

declare(strict_types = 1);

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\asset_release\Entity\AssetReleaseInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_bundle_class\LogoInterface;
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
 * Prepares some variables for all search result view modes.
 *
 * @param array $variables
 *   The variables array.
 * @param string $entity_type_id
 *   The entity type ID.
 */
function _ventuno_preprocess_search_result(array &$variables, string $entity_type_id): void {
  $entity = $variables[$entity_type_id];
  $bundle_key = \Drupal::entityTypeManager()->getDefinition($entity_type_id)->getKey('bundle');
  $variables['text'] = $entity instanceof DescriptionInterface ? $variables['content'][$entity->getDescriptionField()] : NULL;
  $variables['topics'] = ($entity instanceof TopicReferencingEntityInterface) ? $entity->getTopics() : NULL;
  if ($variables['topics']) {
    $num_of_items = count($variables['topics']);
    if ($num_of_items > 3) {
      $extra_items = $num_of_items - 3;
      $variables['topics'] = array_splice($variables['topics'], 0, 3);
      // The structure name => value mimics the way topics are accessed in the
      // template.
      // @see node--search-result.html.twig.
      // @see node--search-result-featured.html.twig.
      // @see rdf-entity--search-result.html.twig.
      // @see rdf-entity--search-result-featured.html.twig.
      $variables['topics'][]['name']['value'] = t('+:number topics', [
        ':number' => $extra_items,
      ]);
    }
  }

  $variables['type'] = strtoupper($entity->get($bundle_key)->entity->getSingularLabel());
  if ($created_time = $entity->getCreatedTime()) {
    $variables['creation'] = t('@label: @created', [
      '@label' => t('Created'),
      '@created' => \Drupal::service('date.formatter')->format($created_time, 'date_only'),
    ]);
  }
  if ($changed_time = $entity->getChangedTime()) {
    $variables['changed'] = t('@label: @changed', [
      '@label' => t('Updated Date'),
      '@changed' => \Drupal::service('date.formatter')->format($changed_time, 'date_only'),
    ]);
  }

  if ($entity instanceof AssetReleaseInterface) {
    $variables['label'] = new TranslatableMarkup('@label | @version', [
      '@label' => $entity->label(),
      '@version' => $entity->getVersion(),
    ]);
  }

  if ($variables['view_mode'] === 'search_result_featured') {
    $variables['image'] = ($entity instanceof LogoInterface) && $entity->getLogoAsFile()
      ? $entity->getLogoAsRenderArray($variables['view_mode'])
      : _ventuno_get_default_icon_render_array($entity->getEntityTypeId(), $entity->bundle());
  }
}

/**
 * Returns the default icon for the given bundle or the generic entity icon.
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
      'event' => 'calendar3',
      'news' => 'newspaper',
      'video' => 'file-earmark-play',
    ],
    'rdf_entity' => [
      'collection' => 'folder2-open',
      'licence' => 'file-earmark-medical',
      'asset_release' => 'file-earmark-zip',
      'solution' => 'clipboard-check',
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

// @todo ISAICP-7491 enable author. Pasted from joinup_theme.theme
/**
 * Get an inline autor.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The node, where we get the author.
 *
 * @return array
 *   Renderable array contains the author.
 */
function _ventuno_get_author(NodeInterface $node) {
  /** @var \Drupal\Core\Entity\EntityViewBuilderInterface $view_builder */
  // @todo ISAICP-7491 Allow ventuno_preprocess_page_title__node__news to get the author
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('user');
  $author = $node->get('uid')->entity;
  $author_inline = $view_builder->view($author, 'inline');

  return $author_inline;
}
