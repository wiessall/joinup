<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Exception\ProviderFetchException;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Code hosting platform provider plugin.
 *
 * @EuOssCatalogueProvider(
 *   id = "hosting_platform",
 *   label = @Translation("Hosting platform"),
 *   description = @Translation("Code hosting platform"),
 *   parser = "publiccode_yml",
 *   deriver = "\Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Deriver\HostingPlatformDeriver",
 *   configuredBy = "hosting_platform",
 * )
 */
class HostingPlatform extends EuOssCatalogueProviderPluginBase implements ConfigurableInterface {

  /**
   * The entity that is configuring this plugin instance.
   */
  protected HostingPlatformConfigInterface $entity;

  /**
   * The code hosting platform instance.
   */
  protected EuOssCatalogueHostingPlatformInterface $platform;

  /**
   * Whether fetching repositories is over.
   *
   * @todo Implement pagination.
   */
  protected bool $done = FALSE;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $parserManager
   *   The EU OSS Catalogue parser plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $platformManager
   *   The code hosting platform plugin manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected LoggerChannelInterface $logger,
    protected EuOssCataloguePluginManagerInterface $platformManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $parserManager, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('plugin.manager.eu_oss_catalogue.parser'),
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    // @todo This is a PoC basic approach. Performance should be considered.
    // @todo Implement pagination.
    if ($this->done) {
      return;
    }

    $requestedFiles = ['publiccodeYml' => 'publiccode.yml'];
    try {
      foreach ($this->getPlatform()->getProjects($requestedFiles) as $project) {
        \assert($project instanceof Project);
        if (array_key_exists('publiccodeYml', $project->files)) {
          yield $project;
        }
      }
    }
    catch (HostingPlatformFetchException $exception) {
      throw new ProviderFetchException(
        $exception->getMessage(),
        0,
         $exception->getPrevious()
      );
    }

    $this->done = TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    if (!$this->configuration) {
      $this->configuration = $this->getEntity()->getPlatformSettings();
      $this->configuration['id'] = $this->getEntity()->getPlatformPluginId();
    }
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'platformId' => NULL,
      'platformSettings' => [],
    ];
  }

  /**
   * Returns the configuration entity used to configure this plugin instance.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface
   *   The coe hosting platform configuration entity.
   */
  protected function getEntity(): HostingPlatformConfigInterface {
    if (!isset($this->entity)) {
      /** @var \Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface $entity */
      $entity = $this->entityTypeManager->getStorage('hosting_platform')->load($this->getDerivativeId());
      if (!$entity) {
        throw new \RuntimeException("Cannot load the '{$this->getDerivativeId()}' config entity");
      }
      $this->entity = $entity;
    }
    return $this->entity;
  }

  /**
   * Returns the code hosting platform plugin instance.
   *
   * @return \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface
   *   The code hosting platform plugin instance.
   */
  protected function getPlatform(): EuOssCatalogueHostingPlatformInterface {
    if (!isset($this->platform)) {
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $platform */
      $platform = $this->platformManager->createInstance($this->getEntity()->getPlatformPluginId(), $this->getEntity()->getPlatformSettings());
      $this->platform = $platform;
    }
    return $this->platform;
  }

}
