<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Taxonomy term resolver.
 *
 * @EuOssCatalogueResolver(
 *   id = "taxonomy_term",
 *   label = "Taxonomy term",
 * )
 */
class TaxonomyTermResolver extends DefaultResolver implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin ID for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected LoggerChannelInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'vid' => NULL,
      'controlled' => FALSE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(FieldItemListInterface $fieldItemList, Project $project, array $metadata): array {
    $originalValue = parent::resolve($fieldItemList, $project, $metadata);

    // Convert to array to handle also the multiple terms case.
    $names = (array) $originalValue;

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Variable '$names' or $originalValue might be an empty array.
    $termIds = [];
    if ($names) {
      $termIds = array_values($storage->getQuery()->accessCheck(FALSE)
        ->condition('vid', $this->getConfiguration()['vid'])
        ->condition('name', $names, 'IN')
        ->execute());
    }

    $controlled = $this->getConfiguration()['controlled'] ?? FALSE;

    $tids = [];
    foreach ($storage->loadMultiple($termIds) as $tid => $term) {
      if (($delta = array_search($term->label(), $names)) !== FALSE) {
        $tids[$names[$delta]] = $tid;
      }
    }

    $missingTermNames = array_diff($names, array_keys($tids));

    if ($controlled && !empty($missingTermNames)) {
      // Some terms don't exist in this controlled vocabulary.
      throw new UnresolvableValueException($fieldItemList->getName(), $this->getConfiguration()['path'], $originalValue, $metadata);
    }

    foreach ($missingTermNames as $name) {
      $tids[$name] = $storage->create([
        'vid' => $this->getConfiguration()['vid'],
        'name' => $name,
      ]);
    }

    // Preserve the original order.
    $tids = array_values(array_merge(array_flip($names), $tids));

    $fieldItemList->setValue($tids);

    return $tids;
  }

}
