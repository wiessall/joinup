<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Gherkin\Node\TableNode;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Driver\Entity\EntityStub;

/**
 * Helper methods to deal with entities.
 */
trait EntityTrait {

  use NameMappingsTrait;

  /**
   * Finds an entity by label with the given type and bundle.
   */
  protected static function getEntityByLabel(string $entity_type_id, string $label, ?string $bundle = NULL): EntityInterface {
    return static::getEntityByKey('label', $entity_type_id, $label, $bundle);
  }

  /**
   * Finds an entity by ID with the given type and bundle.
   */
  protected static function getEntityById(string $entity_type_id, string $label, ?string $bundle = NULL): EntityInterface {
    return static::getEntityByKey('id', $entity_type_id, $label, $bundle);
  }

  /**
   * Returns the entity with the given type, bundle and key.
   *
   * If multiple entities have the same label then the first one is returned.
   *
   * @param string $key
   *   The entity key to check against.
   * @param string $entity_type_id
   *   The entity type to check.
   * @param string $value
   *   The value to check.
   * @param string|null $bundle
   *   Optional bundle to check. If omitted, the entity can be of any bundle.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The requested entity.
   *
   * @throws \RuntimeException
   *   Thrown when an entity with the given type, label and bundle does not
   *   exist.
   */
  protected static function getEntityByKey(string $key, string $entity_type_id, string $value, ?string $bundle = NULL): EntityInterface {
    $entity_type_manager = \Drupal::entityTypeManager();
    $storage = $entity_type_manager->getStorage($entity_type_id);
    $entity = $entity_type_manager->getDefinition($entity_type_id);

    $query = $storage->getQuery()
      ->condition($entity->getKey($key), $value)
      ->accessCheck(FALSE)
      ->range(0, 1);

    // Optionally filter by bundle.
    if ($bundle) {
      $query->condition($entity->getKey('bundle'), $bundle);
    }

    $result = $query->execute();

    if ($result) {
      $result = reset($result);
      // Make sure we get a fresh entity from the database, to avoid testing
      // with stale data.
      $storage->resetCache([$result]);
      return $storage->load($result);
    }

    throw new \RuntimeException("The entity with $key '$value' was not found.");
  }

  /**
   * Translates human readable entity types to machine names.
   *
   * @param string $entity_type_label
   *   The human readable entity type. Case insensitive.
   *
   * @return string
   *   The machine name of the entity type.
   */
  protected function translateEntityTypeAlias(string $entity_type_label): string {
    $entity_type_label = strtolower($entity_type_label);
    $aliases = $this->entityTypeAliases();
    if (array_key_exists($entity_type_label, $aliases)) {
      $entity_type_label = $aliases[$entity_type_label];
    }
    return $entity_type_label;
  }

  /**
   * Translates human readable bundle names to machine names.
   *
   * @param string $bundle
   *   The human readable bundle. Case insensitive.
   *
   * @return string
   *   The machine name of the bundle.
   */
  protected function translateBundle(string $bundle): string {
    $bundle = strtolower($bundle);
    $aliases = $this->bundleAliases();
    if (array_key_exists($bundle, $aliases)) {
      $bundle = $aliases[$bundle];
    }
    return $bundle;
  }

  /**
   * Returns the URI of the given entity, prefixed with the base path.
   *
   * Since Behat tests are run from the CLI the base path (which is normally
   * provided as an environment variable by the webserver) is not available on
   * the Symfony router object which is used by Drupal to construct the URI.
   * This returns the URI prefixed with the base path which is set as a global
   * variable in Drupal by the Behat Drupal Extension.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to return the URI.
   * @param string $rel
   *   The link relationship type, for example: canonical or edit-form.
   * @param array $options
   *   See \Drupal\Core\Routing\UrlGeneratorInterface::generateFromRoute() for
   *   the available options.
   *
   * @return string
   *   The URI.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   When the entity doesn't have an ID yet.
   */
  protected function getEntityUri(EntityInterface $entity, string $rel = 'canonical', array $options = []): string {
    $base_path = rtrim(base_path(), '/');
    return $base_path . $entity->toUrl($rel, $options)->toString();
  }

  /**
   * Updates an entity.
   *
   * @codingStandardsIgnoreStart
   *  Table format:
   *  | title               | Sample solution   |
   *  | description         | A sample solution |
   *  | ...                 | ...               |
   * @codingStandardsIgnoreEnd
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The the entity to update.
   * @param \Behat\Gherkin\Node\TableNode $fieldsTable
   *   The values of the fields to update.
   */
  protected function doUpdateEntity(FieldableEntityInterface $entity, TableNode $fieldsTable): void {
    $values = $fieldsTable->getRowsHash();
    $values['type'] = $entity->bundle();
    $stub = new EntityStub($entity->getEntityTypeId(), $entity->bundle(), $values);
    $this->parseEntityFields($stub);
    foreach (array_keys($fieldsTable->getRowsHash()) as $key) {
      $value = $stub->getValue($key);
      if ($key === 'changed' && !is_numeric($value)) {
        $value = strtotime($value);
      }
      if ($entity->getFieldDefinition($key)->getType() === 'entity_reference') {
        $targetEntityType = $entity->getFieldDefinition($key)->getSetting('target_type');
        foreach ($value as $delta => $entry) {
          $targetEntity = \Drupal::entityTypeManager()->getStorage($targetEntityType)->loadByProperties(['name' => $entry]);
          \assert($targetEntity, "No entity found for $key = $entry");
          $targetEntity = reset($targetEntity);
          $value[$delta] = ['target_id' => $targetEntity->id()];
        }
      }
      $entity->set($key, $value);
    }
    $entity->save();
  }

}
