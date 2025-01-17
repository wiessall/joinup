<?php

declare(strict_types=1);

namespace Drupal\whats_new;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\user\UserDataInterface;

/**
 * Helper service for the whats_new module.
 */
readonly class WhatsNewHelper implements WhatsNewHelperInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected UserDataInterface $userData,
    protected CacheTagsInvalidatorInterface $invalidator,
    protected AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function menuHasFeaturedLinks(string $menu_name): bool {
    $query = $this->entityTypeManager->getStorage('menu_link_content')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', $menu_name)
      ->condition('enabled', 1)
      ->condition('live_link', 1);

    return (bool) $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getFlagEnabledMenuLinks(?EntityInterface $entity = NULL): array {
    $query = $this->entityTypeManager->getStorage('menu_link_content')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'support')
      ->condition('enabled', 1)
      ->condition('live_link', 1);

    if ($entity) {
      $system_path = '/' . $entity->toUrl()->getInternalPath();
      $entity_uris = [
        // Support both the alias and the internal URL. The alias can occur if
        // the user copies it from the URL and the internal URL can occur if the
        // user selects the entity from the autocomplete field.
        "internal:{$this->aliasManager->getAliasByPath($system_path)}",
        "entity:node/{$entity->id()}",
      ];

      $query->condition('link.uri', $entity_uris, 'IN');
    }

    return $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function userHasViewedEntity(EntityInterface $entity): bool {
    $value = $this->userData->get('whats_new', $this->currentUser->id(), 'viewed_entity');
    return !empty($value) && $value === $entity->getEntityTypeId() . ':' . $entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function setUserHasViewedEntity(EntityInterface $entity): void {
    $this->userData->set('whats_new', $this->currentUser->id(), 'viewed_entity', $entity->getEntityTypeId() . ':' . $entity->id());
    $this->invalidator->invalidateTags($this->entityTypeManager->getStorage('menu')->load('support')->getCacheTagsToInvalidate());
  }

}
