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
   *
   * @todo For now, this is only a placeholder controller. We, probably, need to
   *   add provider operations, such as 'Fetch', allowing fetching and queueing
   *   in batch operations. Other interactions to be decided. Also, we can add
   *   statistics per-provider and totals.
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7947
   */
  public function collection(): array {
    $rows = [];
    foreach ($this->providerPluginManager->getDefinitions() as $pluginId => $definition) {
      $row = [
        $pluginId,
        $definition['label'],
        [],
      ];
      $rows[] = $row;
    }
    return [
      '#theme' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Name'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
    ];

  }

}
