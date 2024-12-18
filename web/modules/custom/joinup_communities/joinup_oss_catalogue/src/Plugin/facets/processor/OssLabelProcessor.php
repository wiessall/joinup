<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\facets\processor;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facets\Result\ResultInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces the reference ID with its label for Eu Oss Catalogue Provider.
 *
 * @FacetsProcessor(
 *   id = "oss_label_processor",
 *   label = @Translation("Transform Eu Oss Catalogue Provider reference ID into label"),
 *   description = @Translation("Replaces reference ID with labels. Works only with OSS Catalog facet."),
 *   stages = {
 *     "build" = 50,
 *   },
 * )
 */
class OssLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly EuOssCataloguePluginManagerInterface $providerPluginManager,
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
      $container->get('plugin.manager.eu_oss_catalogue.provider')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results): array {
    $labels = $this->providerPluginManager->getDefinitionsAsOptions('label');
    foreach ($results as $result) {
      if ($result instanceof ResultInterface && isset($labels[$result->getDisplayValue()])) {
        $result->setDisplayValue($labels[$result->getDisplayValue()]);
      }
    }

    return $results;
  }

}
