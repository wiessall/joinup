<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Config\Entity\ThirdPartySettingsInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Exception\UnresolvableValueException;
use Drupal\eu_oss_catalogue\Model\Project;
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
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $resolverManager
   *   The EU OSS Catalogue resolver plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU catalogue logger channel.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EuOssCataloguePluginManagerInterface $resolverManager,
    protected LoggerChannelInterface $logger,
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
      $container->get('logger.channel.eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  final public function parse(Project $project, EuOssCatalogueProviderInterface $provider): ?OssSolutionInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // For now, we're using the solution URL as UUID.
    $solutions = $nodeStorage->loadByProperties(['oss_git_url' => $project->url]);

    $solution = $solutions ? reset($solutions) : $nodeStorage->create(['type' => 'oss_solution']);
    \assert($solution instanceof OssSolutionInterface);

    try {
      // Populate project metadata array. This array is used by some parsers to
      // add metadata to the final stored node via resolvers.
      $metadata = $this->getMetadata($project);

      $solution
        ->set('title', $this->getTitle($project, $metadata))
        ->set('oss_git_url', $project->url)
        ->addProvider($provider);

      $this->doParse($solution, $project, $metadata, $provider);
    }
    catch (\Throwable $exception) {
      $this->logger->error('Cannot parse solution @title for @url', [
        '@title' => $solution->label(),
        '@url' => $project->url,
      ]);
      return NULL;
    }

    foreach ($solution->getFields(FALSE) as $fieldName => $fieldItemList) {
      // It's a convention that all fields are prefixed with oss_*.
      if (!str_starts_with($fieldName, 'oss_')) {
        continue;
      }

      $fieldDefinition = $solution->getFieldDefinition($fieldName);
      if ($fieldDefinition instanceof ThirdPartySettingsInterface) {
        if ($settings = $fieldDefinition->getThirdPartySetting('eu_oss_catalogue', $this->getPluginId())) {
          $resolver = $this->getResolver($settings['resolver'], $settings['config']);
          try {
            $resolver->resolve($fieldItemList, $project, $metadata);
          }
          catch (UnresolvableValueException $exception) {
            Error::logException($this->logger, $exception);
            // Fail softly, don't set this field.
            continue;
          }
        }
      }
    }

    return $solution;
  }

  /**
   * Parses project data into the OSS Solution node.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution node.
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param array $metadata
   *   The project's metadata.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   */
  protected function doParse(OssSolutionInterface $solution, Project $project, array $metadata, EuOssCatalogueProviderInterface $provider): void {}

  /**
   * Configures and returns a resolver given its plugin ID and configuration.
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

  /**
   * Returns project's metadata.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   *
   * @return array
   *   The project's metadata.
   */
  protected function getMetadata(Project $project): array {
    return [];
  }

  /**
   * Returns the OSS solution title.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param array $metadata
   *   The project's metadata.
   *
   * @return string
   *   The OSS solution title.
   */
  protected function getTitle(Project $project, array $metadata): string {
    return $project->getFullName();
  }

}
