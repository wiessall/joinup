<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Parser;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\TypedData\MapDataDefinition;
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

  use SchemaCheckTrait {
    checkValue as traitCheckValue;
  }

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
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager interface.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EuOssCataloguePluginManagerInterface $resolverManager,
    protected LoggerChannelInterface $logger,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $this->entityTypeManager, $this->resolverManager, $logger);
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
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkSchema(OssSolutionInterface $solution): array {
    $errorList = $schemaErrors = [];

    try {
      $metadata = Yaml::decode($solution->get('oss_publiccode_yml_content')->getValue());
      // Using the config schema checker. And it works!
      $schemaErrors = $this->checkConfigSchema($this->typedConfigManager, 'eu_oss_catalogue.publiccode_yml', $metadata);
      if (!is_array($schemaErrors) || !$schemaErrors) {
        return $errorList;
      }
    }
    catch (InvalidDataTypeException $exception) {
      $schemaErrors[] = "Cannot decode publiccode.yml for node {$solution->id()}";
    }
    catch (\InvalidArgumentException $exception) {
      // @see \Drupal\Core\Config\Schema\ArrayElement::get()
      if (preg_match("/The configuration property ([^ ].*) doesn't exist./", $exception->getMessage(), $found)) {
        $schemaErrors[$found[1]] = "Path has no schema";
      }
    }

    foreach ($schemaErrors as $key => $error) {
      if (str_contains($key, ':')) {
        // The config name is not relevant, this is not a config.
        [, $key] = explode(':', $key, 2);
      }
      $errorList[] = '<code>' . str_replace('.', '/', $key) . "</code>: $error";
    }
    return $errorList;
  }

  /**
   * {@inheritdoc}
   */
  protected function doParse(OssSolutionInterface $solution, Project $project, array $metadata, EuOssCatalogueProviderInterface $provider): void {
    parent::doParse($solution, $project, $metadata, $provider);

    /** @var \Drupal\Core\Config\Entity\ThirdPartySettingsInterface $definition */
    $definition = $solution->getFieldDefinition('oss_publiccode_yml_version');
    $settings = $definition->getThirdPartySetting('eu_oss_catalogue', $this->getPluginId());
    $this->getResolver($settings['resolver'], $settings['config'])
      ->resolve($solution->get('oss_publiccode_yml_version'), $project, $metadata);

    // Add also the publiccode.yml content.
    $solution->set('oss_publiccode_yml_content', Yaml::encode($metadata));
  }

  /**
   * {@inheritdoc}
   */
  protected function getMetadata(Project $project): array {
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
    return $metadata['name'] ?? parent::getTitle($project, $metadata);
  }

  /**
   * {@inheritdoc}
   *
   * Overriding the original method to provide support for mandatory values as
   * this is not yet implemented in Drupal core.
   *
   * @see https://www.drupal.org/project/drupal/issues/3364108
   * @todo Remove when https://www.drupal.org/project/drupal/issues/3364108.
   */
  protected function checkValue($key, $value): array {
    $errors = $this->traitCheckValue($key, $value);

    /** @var \Drupal\Core\TypedData\TypedDataInterface $element */
    $element = $this->schema->get($key);
    $dataDefinition = $element->getDataDefinition();
    $valueIsProvided = !empty($value) || $value === FALSE;
    if (!$valueIsProvided && !empty($dataDefinition['required'])) {
      $errors[$key] = 'required but empty';
    }
    if ($dataDefinition instanceof MapDataDefinition && is_array($value)) {
      $mapping = $element->getPluginDefinition()['mapping'] ?? [];
      foreach ($mapping as $mapKey => $schema) {
        if (!empty($schema['required']) && !array_key_exists($mapKey, $value)) {
          $errors[$key] = "missing value for '$mapKey'";
        }
      }
    }

    return $errors;
  }

}
