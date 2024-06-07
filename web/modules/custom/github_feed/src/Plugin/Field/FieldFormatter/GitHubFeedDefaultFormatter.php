<?php

declare(strict_types=1);

namespace Drupal\github_feed\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\github_feed\GitHubFeedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'github_feed_default' formatter.
 *
 * @FieldFormatter(
 *   id = "github_feed_default",
 *   label = @Translation("Default"),
 *   field_types = {"github_feed"}
 * )
 */
class GitHubFeedDefaultFormatter extends FormatterBase {

  /**
   * Constructs a new GitHubFeedDefaultFormatter object.
   *
   * @param string $pluginId
   *   The plugin_id for the formatter.
   * @param \Drupal\Component\Plugin\Definition\PluginDefinitionInterface $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $thirdPartySettings
   *   Any third party settings.
   * @param \Drupal\github_feed\GitHubFeedInterface $githubFeed
   *   The GitHub feed client.
   */
  public function __construct($pluginId, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, $label, $view_mode, array $thirdPartySettings, protected GitHubFeedInterface $githubFeed) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $label, $view_mode, $thirdPartySettings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): GitHubFeedDefaultFormatter {
    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('github_feed.github_feed')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    foreach ($items as $delta => $item) {
      if (!filter_var($item->repository, FILTER_VALIDATE_URL)) {
        continue;
      }

      $fetchedItems = $this->githubFeed->fetchItems($item->repository, $item->type, (int) $item->item_num, (int) $item->comment_num);
      if ($fetchedItems === NULL) {
        // Just show a generic failure message.
        $element[$delta] = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['github-feed-error'],
          ],
          'message' => [
            '#markup' => $this->t('The feed is temporarily unavailable.'),
          ],
        ];

        continue;
      }

      $element[$delta] = [
        '#theme' => 'github_feed',
        '#url' => $item->repository,
        '#feed_type' => $item->type,
        '#cache' => [
          'max-age' => $this->getFieldSetting('cache_max_age'),
        ],
      ];

      foreach ($fetchedItems as $fetchedItem) {
        $element[$delta]['#items'][] = $this->getRenderableItem($item->type, $fetchedItem);
      }
    }

    return $element;
  }

  /**
   * Returns the render array of a single item.
   *
   * @param string $type
   *   The fetched item type.
   * @param array $fetchedItem
   *   The fetched item.
   *
   * @return array
   *   The renderable container.
   */
  public function getRenderableItem(string $type, array $fetchedItem): array {
    $container = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['github-feed-item'],
      ],
    ];

    $container[$type] = [
      '#theme' => 'github_feed_' . $type,
      '#item' => $fetchedItem,
    ];

    if (!empty($fetchedItem['comments'])) {
      $comment_items = [];
      foreach ($fetchedItem['comments'] as $comment) {
        $comment_items[] = [
          '#theme' => 'github_feed_comment',
          '#item' => $comment,
        ];
      }
      $container['comments'] = [
        '#theme' => 'github_feed_comments',
        '#attributes' => [
          'class' => ['github-feed-comments'],
        ],
        '#title' => [
          '#markup' => $this->t('Comments'),
        ],
        '#comments' => [
          '#theme' => 'item_list',
          '#list_type' => 'ol',
          '#items' => $comment_items,
        ],
      ];
    }
    return $container;
  }

}
