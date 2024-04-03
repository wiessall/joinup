<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Parser;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueParserPluginBase;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Parser for publiccode.yml data.
 *
 * @see https://yml.publiccode.tools
 *
 * @EuOssCatalogueParser(
 *   id = "publiccode_yml",
 *   label = @Translation("publiccode.yml data"),
 * )
 */
class PublicCodeYml extends EuOssCatalogueParserPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $resolverManager
   *   The EU OSS Catalogue resolver plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU catalogue logger channel.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager interface.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EuOssCataloguePluginManagerInterface $resolverManager,
    protected LoggerChannelInterface $logger,
    protected LanguageManagerInterface $languageManager,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $this->entityTypeManager, $this->resolverManager, $logger, $this->languageManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.eu_oss_catalogue.resolver'),
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('language_manager'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function doParse(OssSolutionInterface $solution, Project $project, array $metadata, EuOssCatalogueProviderInterface $provider): void {
    parent::doParse($solution, $project, $metadata, $provider);

    $solution
      // Set the publiccode.yml content.
      ->set('oss_publiccode_yml_content', Yaml::encode($metadata))
      // Set the publiccode.yml version.
      ->set('oss_publiccode_yml_version', $metadata['publiccodeYmlVersion'] ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  protected function getMetadata(Project $project): array {
    if (!isset($project->files['publiccodeYml'])) {
      $this->logger->warning('publiccodeYml does not exist for @url', [
        '@url' => $project->url,
      ]);
      return [];
    }
    try {
      return Yaml::decode($project->files['publiccodeYml']) ?? [];
    }
    catch (InvalidDataTypeException $exception) {
      $this->logger->warning('Cannot decode publiccode.yml of @url. Message: @message', [
        '@url' => $project->url,
        '@message' => $exception->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getTitle(Project $project, array $metadata): string {
    $title = $metadata['name'] ?? parent::getTitle($project, $metadata);
    // Eliminate multiple spaces.
    $title = preg_replace('/\s+/', ' ', $title);
    return trim($title);
  }

}
