<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;

/**
 * Helper methods for dealing with files.
 */
trait FileTrait {

  use TestingEntitiesTrait;

  /**
   * Saves a file for an entity and returns the file entity.
   *
   * @param string $filename
   *   The file name given by the user.
   * @param string $file_path
   *   The path of the directory where the file should be created.
   *
   * @return \Drupal\file\Entity\File
   *   The file entity.
   *
   * @throws \Exception
   *   Throws an exception when the file is not found.
   */
  protected function createFile(string $filename, string $file_path = ''): FileInterface {
    $source_path = rtrim(realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
    if (!is_file($source_path)) {
      throw new \Exception("File '$filename' was not found at '$source_path'");
    }
    $file_path = rtrim($file_path, DIRECTORY_SEPARATOR);
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $file_base_name = basename($filename);
    $uri = 'public://';
    if ($file_path) {
      $uri .= $file_path . DIRECTORY_SEPARATOR;
      $file_system->prepareDirectory($uri, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    }
    $uri .= $file_base_name;
    $destination = $file_system->copy($source_path, $uri);
    /** @var \Drupal\file\FileInterface $file */
    $file = \Drupal::entityTypeManager()->getStorage('file')->create([
      'uri' => $destination,
      'filename' => $filename,
    ]);
    $file->save();
    $this->entities['file'][$file->id()] = $file;

    return $file;
  }

  /**
   * Handles file fields when creating entities in step definitions.
   *
   * This allows to use the filenames of the files in the fixtures folder (e.g.
   * "logo.png") in step definitions. It will import the file into Drupal, and
   * replace the filename with the ID of the file entity.
   *
   * @param array $values
   *   An associative array, keyed by field name, containing field values of the
   *   entity that is being saved. Any file fields in this array will have their
   *   values changed into File IDs by reference.
   * @param string $entity_type
   *   The entity type of the entity that is being saved.
   * @param string $bundle
   *   The bundle of the entity that is being saved.
   *
   * @throws \Exception
   *   Thrown when a field name is present in the $values array which does not
   *   exist on the specified entity bundle.
   */
  protected function handleFileFields(array &$values, $entity_type, $bundle): void {
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $fields = $entity_field_manager->getFieldDefinitions($entity_type, $bundle);

    foreach ($values as $field_name => $value) {
      if (!isset($fields[$field_name])) {
        throw new \Exception("Field $field_name is not set on entity $entity_type  : $bundle");
      }
      if (empty($value)) {
        continue;
      }
      /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_info */
      $field_info = $fields[$field_name];
      if (in_array($field_info->getType(), ['image', 'file'])) {
        $values[$field_name] = $this->createFile($value)->get('uri')->value;
      }
    }
  }

}
