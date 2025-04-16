<?php

declare(strict_types=1);

namespace Drupal\github_feed\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\github_feed\GitHubFeedInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'github_feed_default' formatter.
 */
#[FieldFormatter(
  id: 'github_feed_default',
  label: new TranslatableMarkup('Default'),
  field_types: [
    'github_feed',
  ],
)]
class GitHubFeedDefaultFormatter extends FormatterBase {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $fieldDefinition,
    array $settings,
    string $label,
    string $view_mode,
    array $thirdPartySettings,
    protected readonly GitHubFeedInterface $githubFeed,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $fieldDefinition, $settings, $label, $view_mode, $thirdPartySettings);
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
    $cacheMaxAge = (int) $this->getFieldSetting('cache_max_age');
    $elements = [];
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        '#lazy_builder' => [
          'github_feed.lazy_builder:renderFeed',
          [
            $item->repository,
            $item->type,
            (int) $item->item_num,
            (int) $item->comment_num,
            $cacheMaxAge,
          ],
        ],
        '#create_placeholder' => TRUE,
      ];
    }

    return $elements;
  }

}
