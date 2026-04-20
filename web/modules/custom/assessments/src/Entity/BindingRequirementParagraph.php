<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Bundle class for binding_requirement paragraphs.
 */
class BindingRequirementParagraph extends Paragraph {

  use EntityReferenceFieldsOwnedTrait;

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    if ($update) {
      $this->onUpdateEntityReferenceFieldsOwned();
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities): void {
    parent::postDelete($storage, $entities);

    foreach ($entities as $entity) {
      \assert($entity instanceof static);
      $entity->onDeleteEntityReferenceFieldsOwned();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createDuplicate() {
    $duplicate = parent::createDuplicate();

    // Deep-copy referenced assessment entities so each duplicated paragraph
    // owns independent references. Without this, editing an initiative in
    // one requirement would affect all requirements sharing the same entity.
    foreach ($this->getEntityReferenceFieldsOwned() as $fieldName) {
      if (!$duplicate->hasField($fieldName)) {
        continue;
      }
      foreach ($duplicate->get($fieldName) as $item) {
        if (($item->entity instanceof AssessmentInterface) && !$item->entity->isNew()) {
          $newEntity = $item->entity->createDuplicate();
          $item->entity = $newEntity;
        }
      }
    }

    // Copy files referenced in the requirement_documents field so each
    // duplicate owns its own file entities. This aligns with the rest API
    // flow and works around the upstream paragraphs duplicate bug
    // https://www.drupal.org/i/3079729.
    $field = $duplicate->get('requirement_documents');
    \assert($field instanceof FileFieldItemList);

    foreach ($field as $fieldItem) {
      $fileEntity = $fieldItem->entity;
      if (!$fileEntity instanceof FileInterface) {
        continue;
      }

      $destinationDir = $fieldItem->getUploadLocation();
      $fieldItem->entity = \Drupal::service(FileRepositoryInterface::class)->copy($fileEntity, $destinationDir);
    }

    return $duplicate;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityReferenceFieldsOwned(): array {
    return ['expression', 'affects', 'has_participation'];
  }

}
