<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct\Hook;

use Drupal\code_of_conduct\CocEntityCrudHandlerInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Code of Conduct module hook implementations for CRUD operations.
 */
class CocCrudHooks {

  public function __construct(
    #[Autowire(service: 'code_of_conduct.crud_handler')]
    readonly protected CocEntityCrudHandlerInterface $cocEntityCrudHandler,
  ) {}

  /**
   * Grants, deletes, revokes or restores memberships.
   */
  #[Hook('node_update')]
  public function updateEntity(EntityInterface $entity): void {
    if ($entity instanceof CollectionInterface) {
      $this->cocEntityCrudHandler->onGroupUpdate($entity);
    }
    elseif ($entity instanceof CustomPageInterface) {
      $this->cocEntityCrudHandler->onCodeOfConductUpdate($entity);
    }
  }

  /**
   * Handles the meta entity when created or updated.
   */
  #[Hook('meta_entity_update')]
  public function updateCollectionSettings(MetaEntityInterface $settings): void {
    if ($settings->bundle() === 'collection_settings') {
      $this->cocEntityCrudHandler->onGroupSettingsInsertOrUpdate($settings);
    }
  }

  /**
   * Handles the meta entity when deleted.
   */
  #[Hook('meta_entity_delete')]
  public function deleteCollectionSettings(MetaEntityInterface $settings): void {
    if ($settings->bundle() === 'collection_settings') {
      $this->cocEntityCrudHandler->onGroupSettingsDelete($settings);
    }
  }

}
