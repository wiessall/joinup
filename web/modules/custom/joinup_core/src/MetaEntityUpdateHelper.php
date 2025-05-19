<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\UpdateException;

/**
 * Helper for meta entity related to updated entity.
 *
 * Created to calculates the share expiration timestamp as done in
 * ShareableContentTrait. Can be removed with the corresponding post update
 * functions.
 */
class MetaEntityUpdateHelper {

  /**
   * Installs config from the sync storage.
   *
   * @param string $configFileName
   *   The name of the config object file.
   *
   * @throws \Drupal\Core\Utility\UpdateException
   *   If there is a problem with the installation.
   */
  public static function installFromConfigSync(string $configFileName): void {
    try {
      self::installEntityFromConfigSyncYaml($configFileName);
    }
    catch (\Exception $exception) {
      throw new UpdateException($exception->getMessage());
    }
  }

  /**
   * Installs the given config from the sync file storage.
   *
   * @param string $configFileName
   *   The name of the config object file.
   *
   * @throws \Exception
   *   If there is a problem with the installation.
   */
  protected static function installEntityFromConfigSyncYaml(string $configFileName): void {
    $fileName = implode(DIRECTORY_SEPARATOR, [
      Settings::get('config_sync_directory'),
      $configFileName,
    ]);

    if (!file_exists($fileName)) {
      throw new \Exception(sprintf(
        "The specified configuration file '%s' cannot be found.",
        $fileName,
      ));
    }

    $entityTypeId = \Drupal::service('config.manager')->getEntityTypeIdByName($configFileName);
    if ($entityTypeId === NULL) {
      throw new \Exception(sprintf(
        "The specified configuration object '%s' cannot be translated to an entity type ID",
        $fileName,
      ));
    }
    $yamlParser = \Drupal::service('serialization.yaml');
    assert($yamlParser instanceof Yaml);
    $config = $yamlParser::decode(file_get_contents($fileName));
    $entityRepository = \Drupal::service('entity.repository');
    assert($entityRepository instanceof EntityRepositoryInterface);

    if (!isset($config['uuid'])) {
      throw new \Exception("The specified configuration does not contain UUID.");
    }

    $preexistingField = $entityRepository->loadEntityByUuid($entityTypeId, $config['uuid']);

    if ($preexistingField) {
      if ($preexistingField->id() !== $config['id']) {
        // This also causes config import exception, we just throw this
        // exception a bit earlier.
        throw new \Exception(sprintf(
          "ID - UUID collision: there is a preexisting configuration entity with the given type and UUID '%s', but its ID is different. ID in config file: '%s'; ID in preexisting config: '%s'",
          $config['uuid'],
          $config['id'],
          $preexistingField->id(),
        ));
      }
      // The entity is already installed probably, we're OK.
      return;
    }

    $configEntity = \Drupal::entityTypeManager()->getStorage($entityTypeId)->create($config);
    if ($configEntity instanceof SynchronizableInterface) {
      $configEntity->setSyncing(TRUE);
    }
    $configEntity->trustData()->save();
  }

}
