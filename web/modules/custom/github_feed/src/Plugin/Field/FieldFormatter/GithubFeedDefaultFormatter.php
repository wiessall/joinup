<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\github_feed\GithubFeed;
use Drupal\github_feed\GithubFeedInterface;
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
class GithubFeedDefaultFormatter extends FormatterBase {

  /**
   * Constructs a new GithubFeedDefaultFormatter object.
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
   * @param \Drupal\github_feed\GithubFeedInterface $githubFeed
   *   The Github feed client.
   */
  public function __construct($pluginId, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, $label, $view_mode, array $thirdPartySettings, protected GithubFeedInterface $githubFeed) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $label, $view_mode, $thirdPartySettings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
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
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];

    foreach ($items as $delta => $item) {
      if (!filter_var($item->repository, FILTER_VALIDATE_URL)) {
        continue;
      }

      $fetchedItems = $this->githubFeed->fetchItems($item->repository, $item->type, (int) $item->item_num, (bool) $item->include_comments, (int) $item->comment_num);
      [$owner, $project] = GithubFeed::getOwnerAndProjectFromUri($item->repository);
      $cacheId = $owner . ':' . $project . ':' . $item->type . ':' . $item->item_num . ':' . $item->include_comments . ':' . $item->comment_num;
      $element[$delta] = [
        '#theme' => 'github_feed',
        '#url' => $item->repository,
        '#type' => $item->type,
        '#cache' => [
          'tags' => ['github_feed:' . $cacheId],
          // Cache for at least a day.
          // @todo Take this from somewhere.
          'max-age' => 86400,
        ],
      ];

      foreach ($fetchedItems as $fetchedItem) {
        $container = [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['github-feed-item'],
          ],
        ];

        $container[$item->type] = [
          '#theme' => 'github_feed_' . $item->type,
          '#item' => $fetchedItem,
        ];

        if (!empty($fetchedItem['comments'])) {
          foreach ($fetchedItem['comments'] as $comment) {
            $comment_items[] = [
              '#theme' => 'github_feed_comment',
              '#item' => $comment,
            ];
          }
          $container['comments'] = [
            '#type' => 'container',
            '#attributes' => [
              'class' => ['github-feed-comments'],
            ],
            'title' => [
              '#markup' => $this->t('Comments'),
            ],
            'comments' => [
              '#theme' => 'item_list',
              '#items' => $comment_items,
            ],
          ];
        }
        $element[$delta]['#items'][] = $container;
      }

    }

    return $element;
  }

}
