<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Model\EuOssCatalogueFetchedSolution;

/**
 * Base plugin for EU OSS Catalogue provider plugins.
 */
abstract class EuOssCatalogueProviderPluginBase extends PluginBase implements EuOssCatalogueProviderInterface {
  /**
   * Static cache of the parser associated with this provider.
   */
  protected EuOssCatalogueParserInterface $parser;

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
   *   The EU OSS parser plugin manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCataloguePluginManagerInterface $parserManager,
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
      $container->get('plugin.manager.eu_oss_catalogue.parser'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterSolution(OssSolutionInterface $solution, EuOssCatalogueFetchedSolution $fetchedSolution): void {
  }

  /**
   * {@inheritdoc}
   */
  public function getParser(): EuOssCatalogueParserInterface {
    if (!isset($this->parser)) {
      /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueParserInterface $parser */
      $parser = $this->parserManager->createInstance($this->getPluginDefinition()['parser']);
      $this->parser = $parser;
    }
    return $this->parser;
  }

}
