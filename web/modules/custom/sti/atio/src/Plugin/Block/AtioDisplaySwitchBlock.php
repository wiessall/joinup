<?php

declare(strict_types=1);

namespace Drupal\atio\Plugin\Block;

use Drupal\atio\AtioDisplaySwitch;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lets the reader switch an innovation page between ATIO fields and claims.
 */
#[Block(
  id: 'atio_display_switch',
  admin_label: new TranslatableMarkup('ATIO display switch'),
  category: new TranslatableMarkup('ATIO'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node'), required: FALSE),
  ],
)]
final class AtioDisplaySwitchBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    private readonly AtioDisplaySwitch $displaySwitch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('atio.display_switch'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->getContextValue('node');
    if (!$node instanceof NodeInterface || !$this->displaySwitch->applies($node)) {
      return [];
    }

    $labels = [
      'both' => $this->t('ATIO fields and claims'),
      'atio' => $this->t('ATIO fields'),
      'claims' => $this->t('Source claims'),
    ];
    $current = $this->displaySwitch->currentMode();

    $items = [];
    foreach (array_keys(AtioDisplaySwitch::MODES) as $mode) {
      $query = $mode === AtioDisplaySwitch::DEFAULT_MODE ? [] : [AtioDisplaySwitch::QUERY_ARG => $mode];
      $attributes = ['class' => ['atio-display-switch__link']];
      if ($mode === $current) {
        $attributes['class'][] = 'is-active';
        $attributes['aria-current'] = 'page';
      }
      $items[$mode] = [
        '#type' => 'link',
        '#title' => $labels[$mode],
        '#url' => $node->toUrl('canonical', ['query' => $query, 'attributes' => $attributes]),
      ];
    }

    return [
      '#theme' => 'item_list',
      '#items' => $items,
      '#attributes' => [
        'class' => ['atio-display-switch', 'nav', 'nav-pills'],
        'aria-label' => $this->t('Show'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [AtioDisplaySwitch::CACHE_CONTEXT]);
  }

}
