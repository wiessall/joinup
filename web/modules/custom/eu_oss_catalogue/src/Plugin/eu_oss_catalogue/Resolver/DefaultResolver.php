<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueResolverInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default and fallback publiccode.yml value resolver.
 *
 * @EuOssCatalogueResolver(
 *   id = "default",
 *   label = "Default",
 * )
 */
class DefaultResolver extends PluginBase implements EuOssCatalogueResolverInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected LoggerChannelInterface $logger,
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
      $container->get('logger.channel.eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'path' => NULL,
      'override' => 'always',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(Project $project, array $metadata, FieldItemListInterface $fieldItemList): array {
    $path = explode('/', $this->getConfiguration()['path']);
    return (array) NestedArray::getValue($metadata, $path);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration + $this->defaultConfiguration();
  }

}
