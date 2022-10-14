<?php

declare(strict_types = 1);

namespace Drupal\joinup_core;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\Entity\MediaType;

/**
 * Video Migrate service.
 */
class VideoFieldMigrate {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity display objects.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepository
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a VideoEmbedMigrate object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepository $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, EntityDisplayRepository $entity_display_repository) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeManager->useCaches(FALSE);
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * Get remote video type.
   *
   * @param string $source
   *   The media source type.
   *
   * @return \Drupal\media\Entity\MediaType
   *   Return media type or false.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getRemoteVideoMediaType(string $source): MediaType {
    $type = $this->entityTypeManager->getStorage('media_type')->loadByProperties(['source' => $source]);
    return reset($type);
  }

  /**
   * Find the video_embed_field field to migrate.
   *
   * @return array
   *   Fields to migrate.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function findFieldsToMigrate(): array {
    $fields = [];
    $fieldConfigStorage = $this->entityTypeManager->getStorage('field_config');
    $fieldConfigQuery = $fieldConfigStorage->getQuery();
    $fieldConfigQuery->condition('entity_type', 'media', '<>');
    $fieldConfigQuery->condition('field_type', 'video_embed_field');
    $result = $fieldConfigQuery->execute();

    foreach ($result as $id) {
      /** @var \Drupal\field\Entity\FieldConfig $fieldConfig */
      $fieldConfig = $fieldConfigStorage->load($id);
      $fields[] = [
        'field_name' => $fieldConfig->getName(),
        'entity_type' => $fieldConfig->getTargetEntityTypeId(),
        'bundle' => $fieldConfig->getTargetBundle(),
        'cardinality' => $fieldConfig->getFieldStorageDefinition()->get('cardinality'),
      ];
    }
    return $fields;
  }

  /**
   * Get the field values for a video_embed_field.
   *
   * @param string $fieldName
   *   Field name.
   * @param string $entityType
   *   Entity type.
   * @param string $bundle
   *   Entity bundle.
   *
   * @return array
   *   Return field values.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getFieldValues(string $fieldName, string $entityType, string $bundle): array {
    $values = [];
    $storage = $this->entityTypeManager->getStorage($entityType);
    $query = $storage->getQuery();
    $query->condition('type', $bundle);
    $query->exists($fieldName);
    $ids = $query->execute();
    if (!empty($ids)) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
      foreach ($storage->loadMultiple(array_values($ids)) as $paragraph) {
        $values[$paragraph->id()] = $paragraph->get($fieldName)->value;
      }
    }
    return $values;
  }

  /**
   * Migrate Video Embed Field to Media.
   *
   * @param \Drupal\media\Entity\MediaType $mediaType
   *   Media type.
   * @param string $refFieldName
   *   Field name.
   * @param string $entityType
   *   Entity type.
   * @param int $id
   *   Entity id.
   * @param string $legacyField
   *   Migration field.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function migrateField(MediaType $mediaType, string $refFieldName, string $entityType, int $id, string $legacyField): void {
    $mediaStorage = $this->entityTypeManager->getStorage('media');
    $storage = $this->entityTypeManager->getStorage($entityType);
    $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType);
    $fieldValues = [];

    /** @var \Drupal\Core\Entity\ContentEntityBase $targetEntity */
    $targetEntity = $storage->load($id);
    /** @var \Drupal\Core\Field\FieldItemList $videoUrlFieldItems */
    $videoUrlFieldItems = $targetEntity->get($legacyField);
    foreach ($videoUrlFieldItems->getIterator() as $videoUrlFieldItem) {
      $videoUrl = $videoUrlFieldItem->value;
      $result = $mediaStorage->getQuery()
        ->condition('bundle', $mediaType->id())
        ->condition($sourceField->getName(), $videoUrl)
        ->execute();
      if (empty($result)) {
        $videoMedia = $mediaStorage->create([
          'bundle' => $mediaType->id(),
          $sourceField->getName() => [
            'value' => $videoUrl,
          ],
        ]);
        $videoMedia->save();
      }
      else {
        $videoMedia = $mediaStorage->load(reset($result));
      }
      $fieldValues[] = ['target_id' => $videoMedia->id()];
    }
    $targetEntity->set($refFieldName, $fieldValues);
    $targetEntity->save();
  }

}
