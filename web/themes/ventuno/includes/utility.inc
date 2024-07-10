<?php

/**
 * @file
 * Utility procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_bundle_class\AbstractInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_core\Controller\IdRedirect;
use Drupal\joinup_oss_catalogue\Entity\JoinupOssSolution;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\smart_trim\TruncateHTML;
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
  /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
  $date_formatter = Drupal::service('date.formatter');
  $bundle_key = \Drupal::entityTypeManager()->getDefinition($entity_type_id)->getKey('bundle');

  // Prepare different data depending on the route.
  $route_match = \Drupal::routeMatch();
  $route_name = $route_match->getRouteName();

  // Only supposed to be passed to horizontal tiles, but also vertical tiles on
  // Homepage and Topic pages. But for custom pages, this is generic.
  $routes_with_text = [
    'page_manager.page_view_homepage_homepage-layout_builder-0',
    'entity.meta_entity.canonical',
  ];
  $is_oss_solution_tile = $variables['view_mode'] === 'view_mode_tile' && $entity instanceof JoinupOssSolution;
  if ((in_array($route_name, $routes_with_text) && $variables['view_mode'] === 'view_mode_tile') ||
    $is_oss_solution_tile ||
    $variables['view_mode'] === 'view_mode_tile_horizontal' ||
    $variables['view_mode'] === 'highlighted' ||
    ($entity_type_id === 'node' && $entity->bundle() === 'custom_page')
  ) {
    if ($entity instanceof AbstractInterface) {
      $variables['text'] = $variables['content'][$entity->getAbstractField()] ?? NULL;
    }
    else {
      $variables['text'] = $entity instanceof DescriptionInterface && isset($variables['content'][$entity->getDescriptionField()]) ? $variables['content'][$entity->getDescriptionField()] : NULL;
    }
  }

  // Image.
  $variables['image'] = ($entity instanceof LogoInterface) && !$entity->get($entity->getLogoFieldName())->isEmpty()
    ? $entity->getLogoAsRenderArray($variables['view_mode'])
    : [];

  // Topics.
  $topics = ($entity instanceof TopicReferencingEntityInterface) ? $entity->getTopics() : NULL;
  if ($topics) {
    $variables['badges'] = _ventuno_terms_to_badges($topics, t('topics'));
  }

  // Bundle label and icon.
  $bundle_type = $entity->get($bundle_key)->entity;
  $variables['bundle']['label'] = ucfirst($bundle_type->getSingularLabel() ?? $bundle_type->label());
  $variables['bundle']['icon'] = _ventuno_get_default_icon($entity->getEntityTypeId(), $entity->bundle());

  // Info (above the title).
  $variables['info']['icon'] = 'calendar-event';
  if ($changed_time = $entity->getChangedTime()) {
    $variables['info']['label'] = t('@label: @changed', [
      '@label' => t('Last update'),
      '@changed' => \Drupal::service('date.formatter')->format($changed_time, 'date_only'),
    ]);
  }

  // Like flag. Meant to be the first "extra-data" displayed in tile's footer.
  $like = $variables['content']['flag_like_node'] ?? $variables['content']['flag_like_rdf_entity'] ?? NULL;
  if ($like !== NULL) {
    $variables['extra_data'][] = ['label' => $like];
  }

  // Iterate over entity bundles for case-specific data.
  switch ($entity->bundle()) {
    case 'discussion':
      $variables['info']['icon'] = 'calendar-event';
      if ($changed_time = $entity->getLatestUpdateTime()) {
        $variables['info']['label'] = t('@label: @changed', [
          '@label' => t('Last activity'),
          '@changed' => $date_formatter->format($changed_time, 'custom', 'd/m/Y, H:i'),
        ]);
      }
      $node = $variables['elements']['#node'];
      $variables['extra_data'][] = [
        'icon' => 'chat',
        'label' => $node->get('field_replies')->comment_count,
      ];
      break;

    case 'event':
      $variables['highlighted']['icon'] = 'calendar-event';
      $variables['highlighted']['text'] = $variables['content']['field_event_date'];
      $variables['info']['icon'] = 'geo-alt';
      $node = $variables['elements']['#node'];
      if (!$node->get('field_location')->isEmpty() && !$node->get('field_event_online_location')->isEmpty()) {
        $variables['info']['label'] = t('Hybrid event');
      }
      elseif ($node->get('field_location')->isEmpty()) {
        $variables['info']['label'] = t('Virtual event');
      }
      else {
        $variables['info']['label'] = t('Physical event');
      }
      break;

    case 'oss_solution':
      unset($variables['info']);
      $variables['badges'] = _ventuno_terms_to_badges($entity->getCategories(), t('categories'), 1);
      // Logo URL is invalid, lets use default image provided by theme.
      if (!isset($variables['image']['0'])) {
        unset($variables['image']);
      }
      if (!$entity->get('oss_software_version')->isEmpty()) {
        $variables['highlighted']['text'] = $entity->get('oss_software_version')->value;
      }

      $indicators = [
        'oss_git_stars',
        'oss_git_open_issues',
        'oss_git_open_pull_requests',
        'oss_git_forks',
      ];

      foreach ($indicators as $indicator) {
        if (isset($variables['content'][$indicator][0])) {
          $variables['indicators'][] = $variables['content'][$indicator];
        }
      }

      break;

    case 'distribution':
      if (isset($variables['content']['field_licence']) &&
        !$entity->get('field_licence')->isEmpty()) {
        $variables['text']['icon'] = [
          '#type' => 'pattern',
          '#id' => 'icon',
          '#name' => 'file-earmark-medical',
          '#size' => 'xs',
        ];
        $variables['text']['label'] = $variables['content']['field_licence'];
      }
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

  if ($entity instanceof ArchivableEntityInterface && $entity->isArchived()) {
    $variables['extra_data'][] = [
      'icon' => 'archive-fill',
      'label' => t('Archived'),
    ];
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
 * @return string
 *   The icon id.
 */
function _ventuno_get_default_icon(string $entity_type_id, string $bundle): string {
  $mapping = [
    'node' => [
      'custom_page' => 'file-earmark-richtext',
      'discussion' => 'chat-left-text',
      'distribution' => 'file-earmark-post',
      'document' => 'file-earmark-text',
      'event' => 'calendar-event',
      'news' => 'newspaper',
      'oss_solution' => 'clipboard2-check',
      'video' => 'file-earmark-play',
    ],
    'rdf_entity' => [
      'collection' => 'folder2-open',
      'licence' => 'file-earmark-medical',
      'asset_release' => 'file-earmark-zip',
      'solution' => 'clipboard2-check',
    ],
  ];

  return $mapping[$entity_type_id][$bundle] ?? 'lightning-charge';
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
  $namespaces = implode('|', array_keys(IdRedirect::getEntityTypeFromPersistentUriNamespace()));
  $uri = $entity instanceof RdfSyncEntityInterface ? $entity->getUri() : $entity->id();
  if (preg_match('{^http://data.europa.eu/(?:' . $namespaces . ')/}', $uri)) {
    return $uri;
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

/**
 * Converts terms to badges.
 *
 * @param \Drupal\taxonomy\TermInterface[] $terms
 *   Entity terms.
 * @param \Drupal\Core\StringTranslation\TranslatableMarkup $extraLabel
 *   Extra label.
 * @param int $maxNumberOfBadges
 *   Max number of badges.
 *
 * @return array
 *   Render array.
 */
function _ventuno_terms_to_badges(array $terms, TranslatableMarkup $extraLabel, int $maxNumberOfBadges = 2): array {
  $badges = [];

  foreach ($terms as $term) {
    $badges[] = [
      'label' => $term->label(),
      'topic_id' => $term->id(),
    ];
  }

  $numOfItems = count($terms);
  // Display the actual topic instead of "+ 1 topic".
  if ($numOfItems > $maxNumberOfBadges + 1) {
    $extraItems = $numOfItems - $maxNumberOfBadges;
    $badges = array_splice($badges, 0, $maxNumberOfBadges);
    $badges[] = [
      'label' => '+' . $extraItems . ' ' . $extraLabel,
      'overflow' => TRUE,
    ];
  }

  return $badges;
}
