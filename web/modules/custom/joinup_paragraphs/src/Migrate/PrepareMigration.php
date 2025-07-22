<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Migrate;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prepares migration structure by creating required paragraph types and field configurations.
 */
final class PrepareMigration implements ContainerInjectionInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected StorageInterface $syncStorage,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('config.storage.sync'),
    );
  }

  /**
   * Prepare paragraph's structure.
   */
  public function prepareParagraphs(): void {
    $config_entities = [
      'paragraphs_type' => [
        'paragraphs.paragraphs_type.resource_file',
        'paragraphs.paragraphs_type.resource_link',
      ],
      'field_storage_config' => [
        'field.storage.paragraph.resource_file',
        'field.storage.paragraph.remote_file_url',
      ],
      'field_config' => [
        'field.field.paragraph.resource_file.resource_file',
        'field.field.paragraph.resource_link.remote_file_url',
      ],
    ];

    $this->createConfigEntities($config_entities);
  }

  /**
   * Prepare node's structure.
   */
  public function prepareNodeFields(): void {
    $config_entities = [
      'field_storage_config' => [
        'field.storage.node.resources',
      ],
      'field_config' => [
        'field.field.node.document.resources',
      ],
    ];

    $this->createConfigEntities($config_entities);
  }

  /**
   * Creates configuration entities from sync storage.
   *
   * @param array $config_entities
   *   Array of configuration entities keyed by storage type.
   */
  protected function createConfigEntities(array $config_entities): void {
    foreach ($config_entities as $storage => $keys) {
      foreach ($keys as $key) {
        $this->entityTypeManager->getStorage($storage)
          ->create($this->syncStorage->read($key))
          ->save();
      }
    }
  }

}
