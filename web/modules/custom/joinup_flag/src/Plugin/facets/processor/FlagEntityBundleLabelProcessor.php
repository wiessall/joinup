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

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly EntityTypeBundleInfoInterface $bundleInfo,
  ) {
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

    $bundle_info = $this->bundleInfo->getAllBundleInfo();
    foreach ($results as $result) {
      $rawValue = $result->getRawValue();
      if (!strpos($rawValue, ':')) {
        continue;
      }
      [$entity_type_id, $bundle_id] = explode(':', $rawValue);
      $info = $bundle_info[$entity_type_id][$bundle_id] ?? NULL;
      if (!$info) {
        continue;
      }

      if (!empty($info['label_count'])) {
        $result->setDisplayValue($this->bundleInfo->getBundleCountLabel($entity_type_id, $bundle_id, $result->getCount(), 'no_count_capitalize'));
      }
      else {
        $result->setDisplayValue($info['label']);
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
