<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Helper methods for dealing with files.
 */
trait FileTrait {

  use AssertTrait;

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
    $source_path = $this->getFileSourcePath($filename);
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

    return $file;
  }

  /**
   * Resolves filenames in a raw Behat file field value to absolute paths.
   *
   * Handles CSV multi-value, positional columns separated by ' - ', and the
   * named 'target_id: filename' column form understood by
   * FileHandler::expand().
   *
   * @param string $value
   *   The raw Behat field value, e.g. "text.pdf, other.pdf - display: 1".
   *
   * @return string
   *   The same string with each filename replaced by its absolute fixture path.
   */
  public function parseFileBehatValue(string $value): string {
    $result = [];
    foreach (str_getcsv($value, escape: "\\") as $entry) {
      $parts = explode(' - ', trim($entry));
      foreach ($parts as $i => $part) {
        $part = trim($part);
        if (!str_contains($part, ': ')) {
          $parts[$i] = $this->getFileSourcePath($part);
        }
      }
      $result[] = implode(' - ', $parts);
    }
    return implode(', ', $result);
  }

  /**
   * Returns the absolute path to the given file name from the fixture folder.
   *
   * @param string $filename
   *   The file name.
   *
   * @return string
   *   The absolute path to the file.
   */
  public function getFileSourcePath(string $filename): string {
    $filesPath = rtrim(realpath(
      $this->getMinkParameter('files_path')
    ), DIRECTORY_SEPARATOR);
    $sourcePath = $filesPath . DIRECTORY_SEPARATOR . $filename;
    $this->assertCondition(
      is_file($sourcePath),
      sprintf(
        "File '%s' was not found at '%s'",
        $filename,
        $sourcePath,
      )
    );
    return $sourcePath;
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

  /**
   * Lists all files in the fixtures folder.
   *
   * @return array
   *   An array of file names found in the fixtures folder.
   */
  protected function listFilesInFixtures(): array {
    $filesPath = rtrim(realpath(
      $this->getMinkParameter('files_path')
    ), DIRECTORY_SEPARATOR);
    $files = [];
    if (is_dir($filesPath)) {
      $dir = new \DirectoryIterator($filesPath);
      foreach ($dir as $fileInfo) {
        if ($fileInfo->isFile()) {
          $files[] = $fileInfo->getFilename();
        }
      }
    }
    return $files;
  }

  /**
   * Creates a paragraph for as a resource paragraph.
   *
   * @param \Drupal\Driver\Entity\EntityStubInterface $node
   *   The entity stub containing the resource information.
   * @param string $fieldName
   *   The name of the field to create the paragraph for, defaults to
   *   'resources'.
   * @param bool $random
   *   Whether to create a random file paragraph or not. This can be used when
   *   the goal is to create a random file paragraph for testing purposes.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The created paragraph.
   */
  protected function createResourceFileParagraph(\Drupal\Driver\Entity\EntityStubInterface $node, string $fieldName = 'resources', bool $random = FALSE): ?Paragraph {
    $fieldValue = $node->getValue($fieldName);
    // Ensure that the value is not empty or a random value is requested.
    assert(!empty($fieldValue) || $random);

    $availableFiles = $this->listFilesInFixtures();
    $type = 'remote';
    if ($random) {
      // If random is true, select a random file from the available files.
      $fileName = $availableFiles[array_rand($availableFiles)];
      $type = 'local';
    }
    elseif (!empty($fieldValue) && in_array($fieldValue, $availableFiles)) {
      // If the field is not empty, use the provided value.
      $fileName = $fieldValue;
      $type = 'local';
    }

    if ($type !== 'remote') {
      $file = $this->createFile($fileName);
      $paragraph = Paragraph::create([
        'type' => 'resource_file',
        'resource_file' => [
          [
            'target_id' => $file->id(),
          ],
        ],
      ]);
    }
    else {
      $paragraph = Paragraph::create([
        'type' => 'resource_link',
        'remote_file_url' => $node->getValue('resources'),
      ]);
    }
    $paragraph->save();
    $node->setValue('resources:target_id', $paragraph->id());
    $node->setValue('resources:target_revision_id', $paragraph->getRevisionId());
    $node->removeValue('resources');

    return $paragraph;
  }

}
