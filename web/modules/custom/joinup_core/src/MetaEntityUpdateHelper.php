<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\SynchronizableInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\UpdateException;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\meta_entity\Entity\MetaEntity;

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

  /**
   * Migrates fields of collection or solution to settings meta entity.
   *
   * @param string $bundle
   *   Collection or solution.
   * @param array $sandbox
   *   Sandbox array.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Utility\UpdateException
   */
  public static function migrateSettingsFields(string $bundle, array &$sandbox): string {
    if (!in_array($bundle, ['collection', 'solution'])) {
      throw new \Exception("Only 'collection', 'solution' or 'solution' are supported.");
    }
    $entity_type_manager = \Drupal::entityTypeManager();
    $node_storage = $entity_type_manager->getStorage('node');
    $nids = $node_storage->getQuery()
      ->condition('type', $bundle)
      ->accessCheck(FALSE)
      ->execute();

    if (!isset($sandbox['nids_to_process'])) {
      $sandbox['nids_to_process'] = $nids;
      $sandbox['total'] = count($sandbox['nids_to_process']);
      $sandbox['processed'] = 0;
      $sandbox[$bundle . 'SettingsCount'] = 0;
    }

    $nidsToProcess = array_splice($sandbox['nids_to_process'], 0, 50);

    foreach ($nidsToProcess as $nid) {
      $entity = $node_storage->load($nid);

      try {
        if (!$entity instanceof SolutionInterface && !$entity instanceof CollectionInterface) {
          throw new \Exception("Entity must be a solution or collection.");
        }
        $meta_entity = MetaEntity::loadOrCreate($bundle . '_settings', $entity);
        $moderation = (int) $entity->get('group_moderation')->value;
        $meta_entity->set('group_moderation', $moderation);
        $meta_entity->set('content_creation', $entity->get('content_creation')->value);
        $meta_entity->set('listing_content', $entity->get('listing_content')->getValue());
        if ($meta_entity->hasField('list_related_entities')) {
          $list_related_entities = (int) $entity->get('list_related_entities')->value;
          $meta_entity->set('list_related_entities', $list_related_entities);
        }
        $meta_entity->save();
        $sandbox[$bundle . 'SettingsCount']++;
      }
      catch (\Exception $e) {
        throw new UpdateException(sprintf(
          "Saving the meta entity of node %s has failed.\n%s",
          $nid,
          $e->getMessage(),
        ));
      }
    }

    $sandbox['processed'] += count($nidsToProcess);
    $sandbox['#finished'] = $sandbox['processed'] / $sandbox['total'];

    return sprintf(
      "Processed %s of %s %s content. Migrated %s entities so far.",
      $sandbox['processed'],
      $sandbox['total'],
      $bundle,
      $sandbox[$bundle . 'SettingsCount'],
    );
  }

}
