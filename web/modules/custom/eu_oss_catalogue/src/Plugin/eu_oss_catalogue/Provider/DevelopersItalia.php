<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Developers Italia provider.
 *
 * @EuOssCatalogueProvider(
 *   id = "developers_italia",
 *   label = @Translation("Developers Italia"),
 *   parser = "publiccode_yml",
 * )
 */
class DevelopersItalia extends EuOssCatalogueProviderPluginBase implements ContainerFactoryPluginInterface {

  use SchemaCheckTrait {
    checkValue as traitCheckValue;
  }

  /**
   * Developers Italia API endpoint.
   *
   * @var string
   */
  protected const ENDPOINT = 'https://api.developers.italia.it/v1/software';

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typedConfigManager
   *   The typed config manager interface.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected Client $httpClient,
    protected LoggerChannelFactoryInterface $logger,
    protected TypedConfigManagerInterface $typedConfigManager
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
      $container->get('http_client'),
      $container->get('logger.factory'),
      $container->get('config.typed'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): array {
    $data = [];
    $next = '';

    do {
      $url = self::ENDPOINT . $next . ($next ? '&' : '?') . 'all=true&page[size]=500';
      $response = $this->httpClient->get($url);
      $read = Json::decode($response->getBody()->getContents());

      $next = $read['links']['next'];

      foreach ($read['data'] as $newItem) {
        $decoded = Yaml::decode($newItem['publiccodeYml']);

        if (!$this->checkSchema($decoded, $newItem['id'])) {
          continue;
        }

        $data[] = new EuOssCatalogueFetchedSolution(
          $decoded['url'],
          $decoded['name'],
          $decoded,
          $newItem['id'],
        );
      }
    } while (!is_null($next));

    return $data;
  }

  /**
   * Checks whether the schema is valid.
   *
   * @param array $data
   *   Public code data.
   * @param string|null $id
   *   The Developers Italia proprietary ID value.
   *
   * @return bool
   *   Whether the schema is valid.
   *
   * @todo Move the schema check into the future parser plugin.
   */
  protected function checkSchema(array $data, ?string $id): bool {
    // Using the config schema checker. And it works!
    $schemaErrors = $this->checkConfigSchema($this->typedConfigManager, 'eu_oss_catalogue.publiccode_yml', $data);

    if (!is_array($schemaErrors) || !$schemaErrors) {
      return TRUE;
    }

    $errorList = [];
    foreach ($schemaErrors as $key => $error) {
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
      '@provider' => $this->getPluginDefinition()['label'],
      '@provider_id' => $id ?? '[not defined]',
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
    /** @var \Drupal\Core\TypedData\TypedDataInterface $element */
    $element = $this->schema->get($key);
    $dataDefinition = $element->getDataDefinition();
    $valueIsProvided = !empty($value) || $value === FALSE;
    if (!$valueIsProvided && !empty($dataDefinition['required'])) {
      return [$key => 'required but empty'];
    }

    $errors = [];
    foreach ($this->traitCheckValue($key, $value) as $path => $text) {
      if (str_contains($path, ':')) {
        // The config name is not relevant, this is not a config.
        [, $path] = explode(':', $path, 2);
      }
      $errors[$path] = $text;
    }

    return $errors;
  }

}
