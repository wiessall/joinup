<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Parser;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueParserPluginBase;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\eu_oss_catalogue\Model\EuOssCatalogueFetchedSolution;
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
  public function checkSchema(EuOssCatalogueFetchedSolution $fetchedSolution): array {
    $errorList = [];

    // Using the config schema checker. And it works!
    $schemaErrors = $this->checkConfigSchema($this->typedConfigManager, 'eu_oss_catalogue.publiccode_yml', $fetchedSolution->data);
    if (!is_array($schemaErrors) || !$schemaErrors) {
      return $errorList;
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
  public function parse(EuOssCatalogueFetchedSolution $fetchedSolution, EuOssCatalogueProviderInterface $provider): OssSolutionInterface {
    $solution = parent::parse($fetchedSolution, $provider);

    /** @var \Drupal\Core\Config\Entity\ThirdPartySettingsInterface $definition */
    $definition = $solution->getFieldDefinition('oss_publiccode_yml_version');
    $settings = $definition->getThirdPartySetting('eu_oss_catalogue', $this->getPluginId());
    $this->getResolver($settings['resolver'], $settings['config'])
      ->resolve($solution->get('oss_publiccode_yml_version'), $fetchedSolution);

    // Add also the publiccode.yml content.
    $solution->set('oss_publiccode_yml_content', Yaml::encode($fetchedSolution->data));

    return $solution;
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

    return $errors;
  }

}
