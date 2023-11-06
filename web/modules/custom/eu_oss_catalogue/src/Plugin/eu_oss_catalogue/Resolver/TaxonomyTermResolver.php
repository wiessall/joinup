<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Drupal\eu_oss_catalogue\Model\Project;
use Psr\Log\LogLevel;
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
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
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
  public function resolve(Project $project, array $metadata, FieldItemListInterface $fieldItemList): array {
    if (!$names = $originalValue = parent::resolve($project, $metadata, $fieldItemList)) {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $termIds = array_values($storage->getQuery()->accessCheck(FALSE)
      ->condition('vid', $this->getConfiguration()['vid'])
      ->condition('name', $names, 'IN')
      ->execute());

    $tids = [];
    foreach ($storage->loadMultiple($termIds) as $tid => $term) {
      if (($delta = array_search($term->label(), $names)) !== FALSE) {
        $tids[$names[$delta]] = $tid;
      }
    }

    $missingTermNames = array_diff($names, array_keys($tids));

    if ($this->getConfiguration()['controlled'] && !empty($missingTermNames)) {
      // Some terms don't exist in this controlled vocabulary.
      $exception = new UnresolvableValueException($fieldItemList->getName(), $this->getConfiguration()['path'], $originalValue, $metadata);
      Error::logException($this->logger, $exception, Error::DEFAULT_ERROR_MESSAGE, [], LogLevel::WARNING);
    }

    foreach ($missingTermNames as $name) {
      $tids[$name] = $storage->create([
        'vid' => $this->getConfiguration()['vid'],
        'name' => $name,
      ]);
    }

    // Preserve the original order.
    return array_values(array_merge(array_flip($names), $tids));
  }

}
