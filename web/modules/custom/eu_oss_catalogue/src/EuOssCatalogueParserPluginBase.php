<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for EU OSS Catalogue parser plugins.
 */
abstract class EuOssCatalogueParserPluginBase extends PluginBase implements EuOssCatalogueParserInterface, ContainerFactoryPluginInterface {

  /**
   * Static cache for resolver plugin instances.
   *
   * @var \Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface[]
   */
  protected array $resolver;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $resolverManager
   *   The EU OSS Catalogue resolver plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PluginManagerInterface $resolverManager,
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
      $container->get('plugin.manager.eu_oss_catalogue.resolver'),
      $container->get('logger.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function parse(EuOssCatalogueFetchedSolution $fetchedSolution, EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');

    // For now, we're using the solution URL as UUID.
    $solutions = $nodeStorage->loadByProperties(['oss_url' => $fetchedSolution->getUrl()]);

    $solution = $solutions ? reset($solutions) : $nodeStorage->create(['type' => 'oss_solution']);
    if (!$solution instanceof OssSolutionInterface) {
      throw new \RuntimeException("Expected 'oss_solution' node, got {$solution->bundle()}");
    }

    $solution->set('title', $fetchedSolution->getName());

    $this->appendProvider($solution, $provider);

    foreach ($solution->getFields(FALSE) as $fieldName => $fieldItemList) {
      // It's a convention that all fields are prefixed with oss_*.
      if (!str_starts_with($fieldName, 'oss_')) {
        continue;
      }

      /** @var \Drupal\field\FieldConfigInterface&\Drupal\Core\Config\Entity\ThirdPartySettingsInterface $fieldConfig */
      if ($fieldConfig = $fieldConfigStorage->load("node.oss_solution.$fieldName")) {
        if ($settings = $fieldConfig->getThirdPartySetting('eu_oss_catalogue', $this->getPluginId())) {
          $resolver = $this->getResolver($settings['resolver'], $settings['config']);
          try {
            $resolvedValue = $resolver->resolve($fetchedSolution, $fieldName);
          }
          catch (UnresolvableValueException $exception) {
            watchdog_exception('eu_oss_catalogue', $exception, $exception->getMessage());
            // Fail softly, don't set this field.
            continue;
          }

          // @todo Handle translations. How?
          $solution->set($fieldName, $resolvedValue);
        }
      }
    }

    return $solution;
  }

  /**
   * Adds a provider plugin ID when the field doesn't yes contain it.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution node entity.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   */
  protected function appendProvider(OssSolutionInterface $solution, EuOssCatalogueProviderInterface $provider): void {
    /** @var \Drupal\pluginreference\Plugin\Field\FieldType\PluginReferenceFieldItemListInterface $source */
    $source = $solution->get('oss_source');
    foreach ($source->getValue() as $value) {
      if ($value['plugin_id'] === $provider->getPluginId()) {
        return;
      }
    }
    $source->appendItem($provider->getPluginId());
  }

  /**
   * Returns a resolver given its plugin ID and configuration.
   *
   * @param string $resolverPluginId
   *   The resolver plugin ID.
   * @param array $configuration
   *   The resolver plugin configuration.
   */
  protected function getResolver(string $resolverPluginId, array $configuration): EuOssCatalogueResolverInterface {
    if (!isset($this->resolver[$resolverPluginId])) {
      $this->resolver[$resolverPluginId] = $this->resolverManager->createInstance($resolverPluginId);
    }
    $this->resolver[$resolverPluginId]->setConfiguration($configuration);
    return $this->resolver[$resolverPluginId];
  }

}
