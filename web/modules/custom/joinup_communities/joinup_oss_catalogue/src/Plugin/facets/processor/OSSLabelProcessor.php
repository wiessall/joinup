<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\facets\processor;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\ResultInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces the reference ID with its label in OSS Catalog.
 *
 * @FacetsProcessor(
 *   id = "oss_label_processor",
 *   label = @Translation("Transform OSS Catalog reference ID into labels"),
 *   description = @Translation("Replaces reference ID with labels. Works only with OSS Catalog facet."),
 *   stages = {
 *     "build" = 50,
 *   },
 * )
 */
class OSSLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new processor plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $providerPluginManager
   *   The EU OSS Catalogue providers plugin manager.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, protected EuOssCataloguePluginManagerInterface $providerPluginManager) {
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
      $container->get('plugin.manager.eu_oss_catalogue.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results): array {
    foreach ($results as $result) {
      if ($result instanceof ResultInterface) {
        $plugin_manager = $this->providerPluginManager->getDefinitionsAsOptions();
        $result->setDisplayValue($plugin_manager[$result->getDisplayValue()]);
      }
    }

    return $results;
  }

}
