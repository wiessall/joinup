<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Traits;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\language\Config\LanguageConfigFactoryOverrideInterface;
use Drupal\language\Config\LanguageConfigOverride;

/**
 * Trait for installing configurations from file system.
 */
trait ConfigInstallTestTrait {

  /**
   * Installs the specified configurations.
   *
   * @param string[] $config_patterns
   *   List of configurations to install. Wildcard (*) character can be used.
   * @param string|null $source
   *   The directory where the configuration YAMLs are stored. Optional.
   */
  protected function installFromConfigCollection(
    array $config_patterns,
    ?string $source = NULL,
  ): void {
    $diff_before = $this->getFieldStorageChangeList();
    $source = rtrim($source ?? DRUPAL_ROOT . '/../config/sync', '/') . '/';

    foreach ($this->getMatchingConfigNames($config_patterns, $source) as $matching_config) {
      $data = Yaml::decode(file_get_contents($source . $matching_config));
      $entity_type_id = \Drupal::service('config.manager')->getEntityTypeIdByName($matching_config);
      if ($entity_type_id) {
        $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
        assert($storage instanceof ConfigEntityStorageInterface);
        $config = $storage->createFromStorageRecord($data);
        if (!empty($preexisting = $storage->load($config->id()))) {
          assert($preexisting instanceof ConfigEntityInterface);
          $config = $storage->updateFromStorageRecord($preexisting, $data);
        }
        $config->setSyncing(TRUE);
      }
      else {
        $config = new Config(
          pathinfo($matching_config, PATHINFO_FILENAME),
          \Drupal::service('config.storage'),
          \Drupal::service('event_dispatcher'),
          \Drupal::service('config.typed')
        );
        $config->initWithData(Yaml::decode(file_get_contents($source . $matching_config)));
      }
      $config->save();
    }

    $diff_after = $this->getFieldStorageChangeList();
    foreach ($diff_after as $entity_type_id => $field_list) {
      $field_changes = array_diff_key($field_list, $diff_before[$entity_type_id] ?? []);
      if (!empty($field_changes)) {
        $changes[$entity_type_id] = $field_changes;
      }
    }
    $update_manager = \Drupal::service('entity.definition_update_manager');
    assert($update_manager instanceof EntityDefinitionUpdateManagerInterface);

    foreach ($changes ?? [] as $entity_type_id => $field_list) {
      $storage_definitions = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
      foreach ($field_list as $name => $change) {
        switch ($change) {
          case EntityDefinitionUpdateManagerInterface::DEFINITION_CREATED:
            $update_manager->installFieldStorageDefinition(
              $name,
              $entity_type_id,
              $storage_definitions[$name]->getProvider(),
              $storage_definitions[$name]
            );
            // Notify the entity storage.
            \Drupal::service('field_storage_definition.listener')->onFieldStorageDefinitionCreate($storage_definitions[$name]);
            break;

          case EntityDefinitionUpdateManagerInterface::DEFINITION_UPDATED:
            $update_manager->updateFieldStorageDefinition(
              $storage_definitions[$name]
            );
            \Drupal::service('field_storage_definition.listener')->onFieldStorageDefinitionUpdate($storage_definitions[$name]);
            break;
        }
      }
    }

    // Without rebuilding the container, some objects, like freshly imported
    // entity bundles, aren't discovered by TypedData manager. There is nothing
    // wrong with this in normal circumstances: After a config import, we
    // usually try to create new entities in a new request, with a freshly
    // instantiated Drupal kernel. But Drupal kernel tests are operating with
    // a single request by default.
    \Drupal::service('kernel')->rebuildContainer();
    // Entity type and entity field manager must not contain outdated info.
    \Drupal::entityTypeManager()->clearCachedDefinitions();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    \Drupal::typedDataManager()->clearCachedDefinitions();
    \Drupal::service('config.typed')->clearCachedDefinitions();
  }

  /**
   * Returns the config file names which name matches the patters.
   *
   * @param string[] $config_patterns
   *   List of config file name patters. Wildcard (*) character can be used.
   * @param string|null $source
   *   The directory where the configuration YAMLs are stored. Optional.
   *
   * @return string[]
   *   File names of the matching configurations, including the extension.
   */
  protected function getMatchingConfigNames(
    array $config_patterns,
    ?string $source = NULL,
  ): array {
    $source = rtrim($source ?? DRUPAL_ROOT . '/../config/common', '/') . '/';
    $all_configs = array_filter(
      scandir($source),
      function (string $name) use ($source) {
        return is_file($source . $name) &&
          in_array(pathinfo($name, PATHINFO_EXTENSION), ['yml', 'yaml']);
      }
    );

    $configurations = [];
    foreach ($config_patterns as $pattern) {
      $pattern_quoted = preg_quote($pattern, '/');
      $pattern = preg_replace('/(?<!\\\)\\\\\\*/', '.+', $pattern_quoted);

      $matching_configs = array_filter(
        $all_configs,
        function (string $name) use ($pattern) {
          return preg_match("/^$pattern/", $name);
        }
      );
      $configurations = array_merge($configurations, $matching_configs);
    }

    return $configurations;
  }

  /**
   * Installs language config overrides of the specified configurations.
   *
   * @param string[] $config_patterns
   *   Name pattern list of configurations which language overrides
   *   (translations) should be installed. Wildcard (*) character can be used.
   * @param string|null $source
   *   The directory where the default configuration collection's YAML files are
   *   located. Optional.
   */
  protected function installLanguageConfigOverridesFromConfigCollection(
    array $config_patterns,
    ?string $source = NULL,
  ): void {
    $module_handler = \Drupal::moduleHandler();
    if (
      !$module_handler->moduleExists('locale') ||
      !$module_handler->moduleExists('language') ||
      !$module_handler->moduleExists('config_translation')
    ) {
      $this->fail('At least one of locale, language or config_translation modules is missing');
    }

    $source = $source ?? DRUPAL_ROOT . '/../config/common/';
    $override_root = $source . 'language';
    if (!is_dir($override_root)) {
      return;
    }
    $languages = array_filter(
      scandir($override_root),
      function (string $subdir) use ($override_root) {
        return is_dir("$override_root/$subdir") &&
          !in_array($subdir, ['.', '..'], TRUE);
      }
    );
    $language_config_factory_override = \Drupal::service('language.config_factory_override');
    $this->assertInstanceof(LanguageConfigFactoryOverrideInterface::class, $language_config_factory_override);
    foreach ($languages as $langcode) {
      $override_source = "$override_root/$langcode/";
      $matching_configs = $this->getMatchingConfigNames($config_patterns, $override_source);
      foreach ($matching_configs as $config_file_name) {
        $config_name = preg_replace('/\.ya?ml$/', '', $config_file_name);
        $language_config_override = $language_config_factory_override->createConfigObject($config_name, "language.$langcode");
        $this->assertInstanceOf(LanguageConfigOverride::class, $language_config_override);
        $data = Yaml::decode(file_get_contents($override_source . $config_file_name));
        $language_config_override->setData($data)->save();
      }
    }
  }

  /**
   * Returns the list of uninstalled entity field storages per entity type ID.
   *
   * @return int[][]
   *   List of uninstalled or updated entity field storages per entity type ID.
   */
  protected function getFieldStorageChangeList(): array {
    $update_manager = \Drupal::service('entity.definition_update_manager');
    assert($update_manager instanceof EntityDefinitionUpdateManagerInterface);
    $list_fields_only = array_filter(
      $update_manager->getChangeList(),
      function (array $changes) {
        return !empty($changes['field_storage_definitions']);
      }
    );
    return array_combine(
      array_keys($list_fields_only),
      array_map(
        function (array $changes) {
          return $changes['field_storage_definitions'];
        },
        $list_fields_only
      )
    );
  }

}
