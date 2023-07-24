<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Controller;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route controllers.
 */
class EuOssCatalogueController extends ControllerBase {

  /**
   * Constructs a new controller class instance.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $providerPluginManager
   *   The EU OSS Catalogue providers plugin manager.
   */
  public function __construct(
    protected PluginManagerInterface $providerPluginManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.eu_oss_catalogue.provider'),
    );
  }

  /**
   * Provides a controller for the 'eu_oss_catalogue.provider.collection' route.
   *
   * @return array
   *   The response as render array.
   */
  public function collection(): array {
    $rows = [];
    foreach ($this->providerPluginManager->getDefinitions() as $pluginId => $definition) {
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider */
      $provider = $this->providerPluginManager->createInstance($pluginId);
      $row = [
        $pluginId,
        $definition['label'],
        [
          'data' => [
            '#markup' => '<code><pre>' . $provider->getDrushCommandHelp() . '</pre></code>',
          ],
        ],
      ];
      $rows[] = $row;
    }
    return [
      '#theme' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Name'),
        $this->t('CLI command'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
    ];

  }

}
