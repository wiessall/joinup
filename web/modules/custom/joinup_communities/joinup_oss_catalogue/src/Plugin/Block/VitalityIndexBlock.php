<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an OSS your software name block.
 *
 * @Block(
 *   id = "eu_oss_catalogue_vitality_index_chart",
 *   admin_label = @Translation("Vitality index chart"),
 *   category = @Translation("OSS Catalogue")
 * )
 */
class VitalityIndexBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new block plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $routeMatch
   *   The current route match service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ResettableStackedRouteMatchInterface $routeMatch,
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
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $solution = $this->routeMatch->getParameter('node');
    if (!$solution instanceof OssSolutionInterface) {
      return [];
    }

    // We're only decoding to check validity.
    try {
      $vitalityIndex = Json::decode($solution->get('oss_vitality_index_range')->value);
    }
    catch (\Throwable $exception) {
      return [];
    }

    if (!is_array($vitalityIndex)) {
      return [];
    }

    return [
      [
        '#type' => 'html_tag',
        '#tag' => 'canvas',
        '#attributes' => [
          'id' => 'vitality-index-chart',
          'data-vitality-index-range' => Json::encode($vitalityIndex),
        ],
        '#attached' => [
          'library' => ['joinup_oss_catalogue/vitality-index-chart'],
        ],
      ],
    ];
  }

}
