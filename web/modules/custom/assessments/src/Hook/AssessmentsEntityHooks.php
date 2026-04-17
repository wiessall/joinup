<?php

declare(strict_types=1);

namespace Drupal\assessments\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\assessments\AssessmentInterface;
use Drupal\file\FileInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\file\Plugin\Field\FieldType\FileFieldItemList;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Entity hooks for the assessments module.
 */
class AssessmentsEntityHooks {

  /**
   * Assessment reference fields that need deep-copying on duplication.
   *
   * @var list<string>
   */
  private const DEEP_COPY_FIELDS = [
    'expression',
    'affects',
    'has_participation',
  ];

  public function __construct(
    protected readonly FileRepositoryInterface $fileRepository,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_duplicate() for paragraph.
   */
  #[Hook('paragraph_duplicate')]
  public function paragraphDuplicate(ParagraphInterface $duplicate, ParagraphInterface $entity): void {
    if ($duplicate->bundle() !== 'binding_requirement') {
      return;
    }

    // Deep-copy referenced assessment entities so each duplicated paragraph
    // owns independent references. Without this, editing an initiative in
    // one requirement would affect all requirements sharing the same entity.
    foreach (self::DEEP_COPY_FIELDS as $field_name) {
      if (!$duplicate->hasField($field_name)) {
        continue;
      }
      foreach ($duplicate->get($field_name) as $item) {
        if ($item->entity instanceof AssessmentInterface) {
          $item->entity = $item->entity->createDuplicate();
        }
      }
    }

    // Copy files referenced in the requirement_documents field so each
    // duplicate owns its own file entities. This aligns with the rest API
    // flow and works around the upstream paragraphs duplicate bug
    // https://www.drupal.org/i/3079729.
    $field = $duplicate->get('requirement_documents');
    \assert($field instanceof FileFieldItemList);

    foreach ($field as $file_item) {
      $file_entity = $file_item->entity;
      if (!$file_entity instanceof FileInterface) {
        continue;
      }

      $destination_dir = $file_item->getUploadLocation();
      $file_item->entity = $this->fileRepository->copy($file_entity, $destination_dir);
    }
  }

}
