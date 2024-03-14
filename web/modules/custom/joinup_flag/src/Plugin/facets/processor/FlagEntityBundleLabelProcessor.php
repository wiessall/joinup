<?php

declare(strict_types=1);

namespace Drupal\joinup_flag\Plugin\facets\processor;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\facets\FacetInterface;
use Drupal\facets\FacetSource\SearchApiFacetSourceInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces the bundle machine name with its label in flag fields.
 *
 * @FacetsProcessor(
 *   id = "flag_entity_bundle_label",
 *   label = @Translation("Transform flag entity bundle into label"),
 *   description = @Translation("Replaces the bundle machine name with its label in flag fields. Use only on flag bundle field."),
 *   stages = {
 *     "build" = 5
 *   }
 * )
 */
class FlagEntityBundleLabelProcessor extends ProcessorPluginBase implements BuildProcessorInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   The entity type bundle info service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager, protected EntityTypeBundleInfoInterface $bundleInfo) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProcessorInterface {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(FacetInterface $facet, array $results): array {
    // Avoid processing if there's no result.
    if (!$results) {
      return [];
    }

    $source = $facet->getFacetSource();
    if (!$source instanceof SearchApiFacetSourceInterface) {
      return $results;
    }

    $bundles = [];
    $bundle_info = $this->bundleInfo->getAllBundleInfo();
    foreach ($results as $result) {
      $bundle_id = $result->getRawValue();
      foreach ($bundle_info as $entity_type_id => $entity) {
        if (isset($entity[$bundle_id])) {
          $bundles[$bundle_id]['entity_type_id'] = $entity_type_id;
          $bundles[$bundle_id]['label'] = $entity[$bundle_id]['label'];
          $bundles[$bundle_id]['label_plural'] = $entity[$bundle_id]['label_plural'] ? ucfirst($entity[$bundle_id]['label_plural']) : $entity[$bundle_id]['label'];
          if (!empty($entity[$bundle_id]['label_count'])) {
            $bundles[$bundle_id]['has_count_label'] = TRUE;
          }
          break;
        }
      }

      if (!isset($bundles[$bundle_id])) {
        continue;
      }

      if (!empty($bundles[$bundle_id]['has_count_label'])) {
        $result->setDisplayValue($bundles[$bundle_id]['label_plural']);
      }
      else {
        $result->setDisplayValue($bundles[$bundle_id]['label']);
      }
    }

    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    return Cache::PERMANENT;
  }

}
