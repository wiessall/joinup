<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A block showing the introduction text on collection and solution overviews.
 */
#[Block(
  id: 'overview_message_block',
  admin_label: new TranslatableMarkup('Overview message'),
)]
class OverviewMessageBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly CurrentRouteMatch $currentRouteMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $routeName = $this->currentRouteMatch->getRouteName();
    $build = [];
    if ($routeName === 'view.solutions.page_1') {
      $build['header_description'] = [
        '#type' => 'inline_template',
        '#template' => '<p>' . $this->getNodeStorage()->load('solution')->getDescription() . '</p>',
      ];
    }
    elseif ($routeName === 'view.collections.page_1') {
      $build['header_description'] = [
        '#type' => 'inline_template',
        '#template' => '<p>' . $this->getNodeStorage()->load('collection')->getDescription() . '</p>',
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $cacheContexts = parent::getCacheContexts();
    return Cache::mergeContexts($cacheContexts, ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), Cache::mergeTags(
      $this->getNodeStorage()->load('collection')->getCacheTagsToInvalidate(),
      $this->getNodeStorage()->load('solution')->getCacheTagsToInvalidate()
    ));
  }

  /**
   * Returns the node type storage.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   *   The node type storage.
   */
  protected function getNodeStorage(): ConfigEntityStorageInterface {
    $storage = $this->entityTypeManager->getStorage('node_type');
    assert($storage instanceof ConfigEntityStorageInterface);
    return $storage;
  }

}
