<?php

declare(strict_types=1);

namespace Drupal\github_feed\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\github_feed\GitHubFeedInterface;
use Drupal\github_feed\GitHubFeedLazyBuilder;
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $plugin_id,
      $plugin_definition,
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
      $lazyBuilderArgs = [
        $item->repository,
        $item->type,
        (int) $item->item_num,
        (int) $item->comment_num,
        $cacheMaxAge,
      ];

      $elements[$delta] = [
        '#lazy_builder' => [
          'github_feed.lazy_builder:buildFeed',
          $lazyBuilderArgs,
        ],
        '#create_placeholder' => TRUE,
        // This portion is render-cached.
        // @phpstan-ignore-next-line invoked with 5 parameters, 4 required.
        '#cache' => GitHubFeedLazyBuilder::getCacheMetadata(...$lazyBuilderArgs),
      ];
    }

    return $elements;
  }

}
