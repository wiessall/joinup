<?php

declare(strict_types=1);

namespace Drupal\joinup_test_content;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\InfoParserInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Service to handle default_content.
 */
class DefaultContentManager {

  use MessengerTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly InfoParserInterface $infoParser,
  ) {
  }

  /**
   * Deletes all default content entities imported by the given module.
   */
  public function deleteDefaultContent(string $module_name): void {
    $module_path = $this->moduleHandler->getModule($module_name)->getPathname();
    $info = $this->infoParser->parse($module_path);
    if (empty($info['default_content'])) {
      return;
    }

    foreach ($info['default_content'] as $entity_type => $uuids) {
      $storage = $this->entityTypeManager->getStorage($entity_type);
      foreach ($uuids as $uuid) {
        $entities = $storage->loadByProperties(['uuid' => $uuid]);
        if (!empty($entities)) {
          $storage->delete($entities);
        }
      }
    }

    $this->messenger()->addStatus($this->t('Deleted all default content entities from @module.', ['@module' => $module_name]));
  }

}
