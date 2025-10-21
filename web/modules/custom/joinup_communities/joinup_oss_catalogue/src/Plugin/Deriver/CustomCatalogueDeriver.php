<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eu_oss_catalogue\Entity\CatalogueType;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for custom catalogue providers from JSON files.
 */
class CustomCatalogueDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static(
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];

    foreach ($this->getValidJsonFiles() as $json_file => $data) {
      $derivative_id = $data['id'];
      $this->derivatives[$derivative_id] = $this->createDerivativeDefinition($data, $json_file, $base_plugin_definition);
    }

    return $this->derivatives;
  }

  /**
   * Gets all valid JSON files with their decoded data.
   *
   * @return array
   *   Array of valid JSON data keyed by file path.
   */
  protected function getValidJsonFiles(): array {
    $valid_files = [];

    $module_path = $this->moduleHandler->getModule('joinup_oss_catalogue')->getPath();
    $data_directory = DRUPAL_ROOT . '/' . $module_path . '/data';

    if (!is_dir($data_directory)) {
      return $valid_files;
    }

    $json_files = glob($data_directory . '/*.json');
    if ($json_files === FALSE) {
      return $valid_files;
    }

    foreach ($json_files as $json_file) {
      $content = file_get_contents($json_file);
      if ($content === FALSE) {
        continue;
      }

      try {
        $data = Json::decode($content);
        if (!is_array($data)) {
          continue;
        }

        if (!isset($data['id'], $data['label'], $data['solutions'])) {
          continue;
        }

        $valid_files[$json_file] = $data;
      }
      catch (\Exception) {
        continue;
      }
    }

    return $valid_files;
  }

  /**
   * Creates a derivative definition from JSON data.
   *
   * @param array $data
   *   The JSON data.
   * @param string $json_file
   *   The JSON file path.
   * @param array $base_plugin_definition
   *   The base plugin definition.
   *
   * @return array
   *   The derivative definition.
   */
  protected function createDerivativeDefinition(array $data, string $json_file, array $base_plugin_definition): array {
    $derivative_id = $data['id'];

    return [
      'id' => "custom:$derivative_id",
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      'label' => $this->t($data['label']),
      'parser' => 'publiccode_yml',
      'type' => isset($data['type']) ? CatalogueType::from($data['type']) : CatalogueType::Local,
      // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
      'description' => isset($data['description']) ? $this->t($data['description']) : NULL,
      'weblinks' => [],
      'country' => NULL,
      'json_file' => $json_file,
      'json_data' => $data,
    ] + $base_plugin_definition;
  }

}
