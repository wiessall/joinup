<?php

declare(strict_types=1);

namespace Drupal\joinup_collection\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\joinup_collection\Entity\CollectionInterface;
use Drupal\og\OgContextInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block plugin to show the collection settings link.
 */
#[Block(
  id: "collection_settings_link",
  admin_label: new TranslatableMarkup("Collection settings link"),
)]
class CollectionSettingsLinkBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\og\OgContextInterface $ogContext
   *   The OG context service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected OgContextInterface $ogContext,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('og.context')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];
    $cache_metadata = (new CacheableMetadata())->addCacheContexts([
      'route',
      'user.roles',
      'og_role',
    ]);

    $collection = $this->ogContext->getGroup();
    if ($collection instanceof CollectionInterface) {
      $cache_metadata->addCacheableDependency($collection);
      $url = Url::fromRoute('joinup_group.settings_form', [
        'node' => $collection->id(),
      ]);
      $build['link'] = [
        '#type' => 'link',
        '#title' => $this->t('Glossary settings'),
        '#url' => $url,
        '#access' => $url->access(),
        '#attributes' => [
          'class' => ['glossary-settings-link'],
        ],
      ];
    }
    $cache_metadata->applyTo($build);

    return $build;
  }

}
