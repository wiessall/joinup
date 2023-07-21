<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Parser;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCatalogueParserPluginBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
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
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager interface.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected LoggerChannelFactoryInterface $logger,
    protected TypedConfigManagerInterface $typedConfigManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $this->logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('logger.factory'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function checkSchema(array $data, EuOssCatalogueProviderInterface $provider, ?string $providerAssignedId = NULL): bool {
    // Using the config schema checker. And it works!
    $schemaErrors = $this->checkConfigSchema($this->typedConfigManager, 'eu_oss_catalogue.publiccode_yml', $data);

    if (!is_array($schemaErrors) || !$schemaErrors) {
      return TRUE;
    }

    $errorList = [];
    foreach ($schemaErrors as $key => $error) {
      if (str_contains($key, ':')) {
        // The config name is not relevant, this is not a config.
        [, $key] = explode(':', $key, 2);
      }
      $errorList[] = '<code>' . str_replace('.', '/', $key) . "</code>: $error";
    }
    $errorListMarkup = "<ul><li>" . implode('</li><li>', $errorList) . "</li></ul>";

    $message = <<<MESSAGE
      <p>@provider ID: <code>@provider_id</code></p>
      <p><code>publiccode.yml</code> schema errors:</p>
      $errorListMarkup
      <p>Full data:</p>
      <pre>
      @data
      </pre>
      MESSAGE;
    $arguments = [
      '@provider' => $provider->getPluginDefinition()['label'],
      '@provider_id' => $providerAssignedId ?? '[not defined]',
      '@data' => Yaml::encode($data),
    ];
    $this->logger->get('eu_oss_catalogue')->error($message, $arguments);

    return FALSE;
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
