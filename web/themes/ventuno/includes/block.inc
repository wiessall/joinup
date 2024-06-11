<?php

/**
 * @file
 * Block specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Url;
use Drupal\topic\Entity\TopicInterface;
use Drupal\topic\Entity\TopicReferencingEntityInterface;

/**
 * Implements hook_preprocess_block().
 */
function ventuno_preprocess_block(array &$variables): void {
  $plugin_id = $variables['elements']['#plugin_id'] ?? NULL;
  switch ($plugin_id) {
    case 'local_actions_block':
      $variables['attributes']['class'][] = 'my-3';
      break;

    case 'entity_unpublished':
      $variables['attributes']['class'][] = 'my-5';
      break;

    case 'sitewide_alert_block':
      $variables['#attached']['library'][] = 'ventuno/alert';
      break;
  }

  // List of the "layout-blocks" (mainly in the Home page) that share common
  // styles.
  $layout_blocks = [
    'homepage_topics-block_1',
    'in_the_spotlight-block',
    'highlighted_content',
    'latest_news-block',
    'highlighted_event',
  ];
  $derivative_plugin_id = $variables['elements']['#derivative_plugin_id'];
  if (in_array($derivative_plugin_id, $layout_blocks)) {
    // Add library and according selectors.
    $variables['#attached']['library'][] = 'ventuno/layout-block';
    $variables['attributes']['class'][] = 'layout-block';
    $variables['title_attributes']['class'][] = 'layout-block__title';
    $variables['title_attributes']['class'][] = 'layout-block__title--' . $derivative_plugin_id;
  }

  $block_id = $variables['elements']['#id'] ?? NULL;
  switch ($block_id) {
    // Add class to navigation bottom links on custom pages.
    case 'ventuno_toc_outline':
      if (isset($variables['attributes']['data-joinup-block-id'])) {
        // Transfer the block plugin ID to the links template.
        $variables['content']['#attributes']['data-joinup-block-id'] = $variables['attributes']['data-joinup-block-id'];
      }
      $variables['#attached']['library'][] = 'ventuno/navigation-bottom';
      $variables['attributes']['class'][] = 'navigation-bottom';
      break;

    case 'ventuno_webtools_etrans':
      $variables['#attached']['library'][] = 'ventuno/etrans';
      $variables['attributes']['class'][] = 'block-oe-webtools-etrans--ventuno';
      break;

    case 'ventuno_like':
      // Avoid ajax throbber being  hidden under eTrans button.
      $variables['attributes']['class'][] = 'zi-2';
      break;
  }

  // List of the blocks to render without contextual links.
  $no_contextual_links_blocks = [
    'add_content',
  ];
  if (in_array($block_id, $no_contextual_links_blocks)
    && isset($variables['title_suffix']['contextual_links'])) {
    unset($variables['title_suffix']['contextual_links']);
  }

  // When rendering some entity within a block, e.g. a menu, in cases such as a
  // preprocess hook of the entity, there is no information about the block
  // that the entity is displayed in. This will allow us to identify such cases.
  $variables['content']['#attributes']['data-joinup-block-id'] = $block_id;
}

/**
 * Implements hook_theme_suggestions_HOOK_alter() for the block hook.
 */
function ventuno_theme_suggestions_block_alter(array &$suggestions, array $variables): void {
  $elements = &$variables['elements'];
  // Adds a dedicated suggestion for a template rendering the "Read more" extra
  // field block for the 4 different entity bundles shown in the "Explore" block
  // on the homepage.
  if (
    $elements['#base_plugin_id'] === 'extra_field_block'
    && preg_match('/extra_field_block:.*:read_more/', $elements['#plugin_id'])
  ) {
    $suggestions[] = 'block__extra_field_block__read_more';
  }
}

/**
 * Implements hook_preprocess_block().
 */
function ventuno_preprocess_block__facetsblock_searchpage(array &$variables): void {
  $block_manager = \Drupal::getContainer()->get('plugin.manager.block');
  $plugin_block = $block_manager->createInstance('joinup_search_clear_filters');
  $clear_button = $plugin_block->build();
  \Drupal::getContainer()->get('renderer')->addCacheableDependency($clear_button, $plugin_block);

  $variables['content'] = [
    $clear_button,
    $variables['content'],
  ];
}

/**
 * Implements hook_preprocess_block().
 */
function ventuno_preprocess_block__group_menu_block__quick_links(array &$variables): void {
  // Hide the block title if there are no links available. Privileged users will
  // still see a help message and be able to access the contextual links.
  if (isset($variables['content']['disabled'])) {
    $variables['label'] = '';
  }
}

/**
 * Implements hook_preprocess_block() for the highlighted content block.
 */
function ventuno_preprocess_block__entityqueue_block__highlighted_content(&$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['elements']['content']['entities'][0]['#node'] ?? NULL;

  // Add a link to related content in the Highlighted Content block which is
  // shown on the homepage. This will lead to the search page with the results
  // filtered by the first referenced topic. If no topics are referenced the
  // link will be hidden.
  if ($node instanceof TopicReferencingEntityInterface) {
    $topics = $node->getTopics();
    $topic = reset($topics);

    if ($topic instanceof TopicInterface) {
      $options = [
        'query' => [
          'f' => [
            'topic:' . $topic->id(),
          ],
        ],
      ];
      $variables['related_content'] = [
        '#type' => 'link',
        '#title' => t('Related content'),
        '#url' => Url::fromRoute('view.search.page_1', [], $options),
        '#attributes' => [
          'class' => ['more-link'],
        ],
      ];
    }
  }
}
