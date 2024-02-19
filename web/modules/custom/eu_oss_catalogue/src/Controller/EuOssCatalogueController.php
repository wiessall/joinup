<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Controller;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Controller\ControllerBase;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides route controllers.
 */
class EuOssCatalogueController extends ControllerBase {

  /**
   * Constructs a new controller class instance.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $providerPluginManager
   *   The EU OSS Catalogue providers plugin manager.
   */
  public function __construct(
    protected EuOssCataloguePluginManagerInterface $providerPluginManager,
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
      $links = [];
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface|NULL $platform */
      $platform = NULL;

      // If the plugin is configured by an entity, add entity operations.
      if (str_contains($pluginId, ':') && ($entityTypeId = $definition['configuredBy'])) {
        $entityType = $this->entityTypeManager()->getDefinition($entityTypeId);
        if ($entityType->hasListBuilderClass()) {
          [, $derivativeId] = explode(PluginBase::DERIVATIVE_SEPARATOR, $pluginId, 2);
          $storage = $this->entityTypeManager()->getStorage($entityTypeId);
          if ($entity = $storage->load($derivativeId)) {
            /** @var \Drupal\Core\Plugin\DefaultSingleLazyPluginCollection $settings */
            $settings = $entity->getPluginCollections()['platformSettings'];
            $platform = $settings->get($entity->getPlatformPluginId());

            $listBuilder = $this->entityTypeManager()->getListBuilder($entityTypeId);
            $links = $listBuilder->getOperations($entity);
          }
        }
      }

      $row = [
          [
            'data' => [
              '#markup' => $pluginId,
              '#prefix' => '<code>',
              '#sufffix' => '</code>',
            ],
          ],
          [
            'data' => [
              [
                '#markup' => $definition['label'],
              ],
              [
                '#markup' => $definition['description'] ?? '',
                '#prefix' => '<div><small>',
                '#suffix' => '</small></div>',
              ],
            ],
          ],
          [
            'data' => [
              '#markup' => $platform?->getLabel(),
            ],
          ],
          [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
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
        $this->t('Code hosting platform'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
    ];
  }

}
