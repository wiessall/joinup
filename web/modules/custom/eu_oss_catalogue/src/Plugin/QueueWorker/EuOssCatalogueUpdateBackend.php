<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginManager;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverPluginManager;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consumes the 'eu_oss_catalogue' queue.
 *
 * @QueueWorker(
 *   id = "eu_oss_catalogue",
 *   title = @Translation("Import EU OSS solutions"),
 *   cron = {"time" = 10},
 * )
 */
class EuOssCatalogueUpdateBackend extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new worker instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginManager $providerManager
   *   The EU OSS Catalogue provider plugin manager service.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueResolverPluginManager $resolverManager
   *   The EU OSS Catalogue resolver plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EuOssCatalogueProviderPluginManager $providerManager,
    protected EuOssCatalogueResolverPluginManager $resolverManager,
    protected LoggerChannelFactoryInterface $logger,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.eu_oss_catalogue.provider'),
      $container->get('plugin.manager.eu_oss_catalogue.resolver'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $fetched = $data['fetched'];
    if (!$fetched instanceof EuOssCatalogueFetchedSolution) {
      throw new \InvalidArgumentException("Invalid data in 'eu_oss_catalogue' queue");
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');
    $pluginDefinition = $this->providerManager->getDefinition($data['provider']);

    $solutions = $nodeStorage->loadByProperties(['oss_url' => $fetched->url]);
    /** @var \Drupal\node\NodeInterface $solution */
    $solution = empty($solutions) ? $nodeStorage->create(['type' => 'oss_solution']) : reset($solutions);

    $solution->set('title', $fetched->name);
    foreach ($solution->getFields(FALSE) as $fieldName => $fieldItemList) {
      if (!str_starts_with($fieldName, 'oss_')) {
        continue;
      }

      /** @var \Drupal\field\FieldConfigInterface&\Drupal\Core\Config\Entity\ThirdPartySettingsInterface $fieldConfig */
      if ($fieldConfig = $fieldConfigStorage->load("node.oss_solution.$fieldName")) {
        $parser = $pluginDefinition['parser'];
        if ($settings = $fieldConfig->getThirdPartySetting('eu_oss_catalogue', $parser)) {
          $resolverPluginId = $settings['resolver'] ?? 'default';
          unset($settings['resolver']);
          /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface $resolver */
          $resolver = $this->resolverManager->createInstance($resolverPluginId, $settings);
          try {
            $resolvedValue = $resolver->resolve($fetched, $fieldName);
          }
          catch (UnresolvableValueException $exception) {
            if ($fieldItemList->getFieldDefinition()->isRequired()) {
              // This field is required but the resolver failed to resolve.
              $message = <<<MESSAGE
                <p>Possible schema violation:</p>
                <p>Drupal field: <code>@field</code></p>
                <p>@provider ID: <code>@provider_id</code></p>
                <p>Path: <code>@path</code></p>
                <p>Value:</p>
                <pre>
                @value
                </pre>
                <p>Full data:</p>
                <pre>
                @data
                </pre>
                MESSAGE;
              $arguments = [
                '@field' => $exception->fieldName,
                '@provider' => $pluginDefinition['label'],
                '@provide_id' => $fetched->providerAssignedId ?? '[not defined]',
                '@path' => $exception->path,
                '@value' => Yaml::encode($exception->value),
                '@data' => Yaml::encode($exception->data),
              ];
              $this->logger->get('eu_oss_catalogue')->error($message, $arguments);
            }
            // Don't set this field.
            continue;
          }

          // @todo Handle translations. How?
          $solution->set($fieldName, $resolvedValue);
        }
      }
    }
    $solution->save();
  }

}
