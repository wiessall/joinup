<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Helper methods to deal with entities.
 */
trait EntityTrait {

  /**
   * Finds and entity by label with given type and bundle.
   */
  protected static function getEntityByLabel(string $entity_type_id, string $label, ?string $bundle = NULL): EntityInterface {
    return static::getEntityByKey('label', $entity_type_id, $label, $bundle);
  }

  /**
   * Finds and entity by ID with given type and bundle.
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
   * Checks the number of available entities filtered by entity type and bundle.
   *
   * @param string $entityTypeId
   *   The entity type.
   * @param string $bundle
   *   The bundle of the entity.
   * @param int $number
   *   The expected number of entities.
   *
   * @throws \Exception
   *   Thrown when the number of entities does not
   *   match the expectation.
   */
  protected function assertEntityCount(string $entityTypeId, string $bundle, int $number): void {
    $typeKey = \Drupal::entityTypeManager()->getDefinition($entityTypeId)->getKey('bundle');
    $actual = \Drupal::entityQuery($entityTypeId)
      ->accessCheck(FALSE)
      ->condition($typeKey, $bundle)
      ->count()
      ->execute();
    if ($actual != $number) {
      throw new \Exception("Wrong number of $bundle entities. Expected number: $number, actual number: $actual.");
    }
  }

  /**
   * Mapping of human readable entity type names to machine names.
   *
   * @return array
   *   The entity type mapping.
   */
  protected static function entityTypeAliases(): array {
    return [
      'collection' => 'node',
      'contact information' => 'node',
      'content' => 'node',
      'custom page' => 'node',
      'discussion' => 'node',
      'distribution' => 'node',
      'document' => 'node',
      'event' => 'node',
      'glossary' => 'node',
      'group' => 'rdf_entity',
      'news' => 'node',
      'owner' => 'node',
      'oss_solution' => 'node',
      'release' => 'node',
      'solution' => 'node',
      'spdx licence' => 'rdf_entity',
      'topic' => 'taxonomy_term',
    ];
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
  protected static function translateEntityTypeAlias(string $entity_type_label): string {
    $entity_type_label = strtolower($entity_type_label);
    $aliases = self::entityTypeAliases();
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
  protected static function translateBundle(string $bundle): string {
    $bundle = strtolower($bundle);
    $aliases = static::bundleAliases();
    if (array_key_exists($bundle, $aliases)) {
      $bundle = $aliases[$bundle];
    }
    return $bundle;
  }

  /**
   * Mapping of human readable bundle names to machine names.
   *
   * @return array
   *   The bundle mapping.
   */
  protected static function bundleAliases(): array {
    return [
      'custom page' => 'custom_page',
      'discussion' => 'discussion',
      'distribution' => 'distribution',
      'document' => 'document',
      'event' => 'event',
      'glossary' => 'glossary',
      'news' => 'news',
      'oss_solution' => 'oss_solution',
      'release' => 'release',
      'spdx licence' => 'spdx_licence',
    ];
  }

  /**
   * Returns a MenuLinkContent entity from the database.
   *
   * The function assumes that there are not duplicate entries with the same
   * title so it returns the first of the results.
   *
   * @param string $title
   *   The label of the menu item.
   * @param string $group_id
   *   The group ID of the menu item.
   * @param string $menu_type
   *   The bundle of the OG menu instance.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface
   *   The MenuLinkContent entity.
   *
   * @throws \Exception
   *    Thrown when the menu item is not found.
   */
  public function getMenuLinkByTitle(string $title, string $group_id, string $menu_type): MenuLinkContentInterface {
    $entity_type_manager = \Drupal::getContainer()->get('entity_type.manager');
    $menu_helper = \Drupal::getContainer()->get('joinup_menu.menu_helper');
    $navigation_menu = $menu_helper->getOgMenuByGroup($menu_type, $group_id);
    $menu_links = $entity_type_manager->getStorage('menu_link_content')->loadByProperties(
      [
        'title' => $title,
        'menu_name' => 'ogmenu-' . $navigation_menu->id(),
      ]
    );
    if (empty($menu_links)) {
      throw new \Exception("The menu link with title '{$title}' was not found.");
    }
    // If there are more that one results, we pick up the newest in order to
    // avoid leftovers produced by previous tests.
    krsort($menu_links);
    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menu_link */
    $menu_link = reset($menu_links);

    return $menu_link;
  }

  /**
   * Forces a reindex of the entity in search_api.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to reindex.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when an entity with a non-existing storage is passed.
   */
  protected function forceSearchApiReindex(ContentEntityInterface $entity): void {
    // Invalidate any static cache, so that all computed fields are calculated
    // with updated values (e.g. the "collection" computed field of solutions).
    \Drupal::entityTypeManager()->getStorage($entity->getEntityTypeId())->resetCache([$entity->id()]);
    /** @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager $content_tracking_manager */
    $content_tracking_manager = \Drupal::service('search_api.entity_datasource.tracking_manager');
    $content_tracking_manager->trackEntityChange($entity);
  }

  /**
   * Navigates to the edit or delete form of an entity.
   *
   * @param string $action
   *   The action. Either 'edit' or 'delete'.
   * @param string $title
   *   The title of the entity.
   * @param string $bundle
   *   An alias of a bundle as defined in the
   *   \Drupal\joinup\Traits\EntityTrait::bundleAliases method.
   *
   * @throws \InvalidArgumentException
   *   Thrown when an invalid action is passed.
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Thrown when the form URL cannot be generated for the community content.
   */
  public function visitEntityForm(string $action, string $title, string $bundle): void {
    if (!in_array($action, ['edit', 'delete'])) {
      throw new \InvalidArgumentException('Only "edit" and "delete" actions are allowed.');
    }

    $entity_type_id = $this->translateEntityTypeAlias($bundle);

    $bundle = $this->translateBundle($bundle);
    $node = $this->getEntityByLabel($entity_type_id, $title, $bundle);
    $path = $node->toUrl("{$action}-form")->getInternalPath();
    $this->visitPath($path);
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

}
