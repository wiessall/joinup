<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityReferenceTrait;
use Drupal\joinup\Traits\FileTrait;

/**
 * Behat step definitions for testing media.
 */
class MediaContext extends RawDrupalContext {

  use EntityReferenceTrait;
  use FileTrait;

  /**
   * Creates media entities.
   *
   * Table format:
   * @code
   * | name      | owner | group              |
   * | Landscape | Joe   | The Art Collection |
   * @endcode
   * See self::fieldMap() for allowed and mandatory fields.
   *
   * @param string $mediaTypeLabel
   *   The label of the media type.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The media data.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   */
  #[Given('the following :media_type_id media:')]
  public function createMedia(string $mediaTypeLabel, TableNode $table): void {
    $entityTypeManager = \Drupal::entityTypeManager();
    $mediaTypes = $entityTypeManager->getStorage('media_type')
      ->loadByProperties(['label' => $mediaTypeLabel]);
    if (!$mediaTypes) {
      throw new \InvalidArgumentException("Invalid media type label '$mediaTypeLabel'.");
    }
    $mediaTypeId = key($mediaTypes);

    $header = $table->getRow(0);
    if ($invalidColumns = array_diff($header, array_keys(static::fieldMap($mediaTypeId)))) {
      throw new \InvalidArgumentException("Invalid column(s): '" . implode("', '", $invalidColumns) . "'");
    }
    if ($missingColumns = array_diff(static::mandatoryFields($mediaTypeId), $header)) {
      throw new \InvalidArgumentException("Missing mandatory column(s): '" . implode("', '", $missingColumns) . "'");
    }

    $revision = time();

    $mediaStorage = $entityTypeManager->getStorage('media');
    foreach ($table->getColumnsHash() as $row) {
      if (isset($row['revision'])) {
        $revision = strtotime($row['revision']);
        unset($row['revision']);
      }
      $name = $row['name'];
      $values = ['bundle' => $mediaTypeId] + $row;
      $this->prepareFields($values);
      $media = $mediaStorage->create($values);
      $media->setNewRevision();
      $media->setName($name);
      $media->setRevisionLogMessage('first revision');
      $media->setCreatedTime($revision);
      $media->setChangedTime($revision);
      $media->setRevisionCreationTime($revision);
      $media->save();
    }
  }

  /**
   * Prepares the fields before creating the entity.
   *
   * @param array $values
   *   The values as they are read from the feature file, passed by reference.
   */
  public function prepareFields(array &$values): void {
    if (isset($values['published'])) {
      $status = ['no', 'yes'];
      if (!in_array($values['published'], $status, TRUE)) {
        throw new \InvalidArgumentException("The 'published' column value should be 'yes' or 'no'.");
      }
      $values['published'] = array_flip($status)[$values['published']];
    }

    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $definitions */
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('media', $values['bundle']);
    foreach (['image', 'file'] as $name) {
      if (isset($values[$name])) {
        $definition = $definitions[static::realField($values['bundle'], $name)];
        $file_directory = $definition->getSetting('file_directory');
        $values[$name] = $this->createFile($values[$name], $file_directory)->id();
      }
    }

    // Special treatment for image's 'alt' attribute.
    if (isset($values['image']) && isset($values['alt'])) {
      // @see \Drupal\DrupalExtension\Context\RawDrupalContext::parseEntityFields()
      $values['image'] = "target_id: {$values['image']} - alt: {$values['alt']}";
      unset($values['alt']);
    }

    // Map human-readable fields to real Drupal entity API fields.
    foreach ($values as $name => $value) {
      if ($name !== 'bundle') {
        $fieldName = static::realField($values['bundle'], $name);
        if ($name !== $fieldName) {
          $values[$fieldName] = $value;
          unset($values[$name]);
        }
      }
    }

    // Temporary convert to \stdClass.
    $values = (object) $values;
    $this->parseEntityFields('media', $values);
    $values = (array) $values;

    $values = $this->convertEntityReferencesValues('media', $values['bundle'], $values);
  }

  /**
   * Returns the real field given its human-readable name.
   *
   * @param string $mediaTypeId
   *   The ID of the media type.
   * @param string $name
   *   The human-readable name of the field.
   *
   * @return string
   *   The real field name.
   */
  protected static function realField(string $mediaTypeId, string $name): string {
    return static::fieldMap($mediaTypeId)[$name]['field'];
  }

  /**
   * Returns a list of mandatory fields for a given media type.
   *
   * @param string $mediaTypeId
   *   The ID of the media type.
   *
   * @return array
   *   A list of mandatory fields for a given media type.
   */
  protected static function mandatoryFields(string $mediaTypeId): array {
    return array_keys(array_filter(static::fieldMap($mediaTypeId), function (array $info): bool {
      return empty($info['optional']);
    }));
  }

  /**
   * Returns the media fields map given a meta type ID.
   *
   * @param string $mediaTypeId
   *   The ID of the media type.
   *
   * @return array
   *   Associative array keyed by the human-readable field name. Values are
   *   associative arrays with the following structure:
   *   - field: (string) The Drupal entity API field name.
   *   - optional: (optional, bool) Whether the field is optional.
   */
  protected static function fieldMap(string $mediaTypeId): array {
    $commonMap = [
      'name' => ['field' => 'name'],
      'owner' => ['field' => 'uid', 'optional' => TRUE],
      'published' => ['field' => 'status', 'optional' => TRUE],
      'uuid' => ['field' => 'uuid', 'optional' => TRUE],
      'revision' => ['field' => 'revision_created', 'optional' => TRUE],
    ];
    $imageLibraryField = ['image' => ['field' => 'image_library_widget_image']];
    $groupField = ['group' => ['field' => 'og_audience']];
    $bundleMap = [
      'collection_banner' => $imageLibraryField,
      'collection_logo' => $imageLibraryField,
      'event_logo' => $imageLibraryField,
      'file' => $groupField + ['file' => ['field' => 'file']],
      'image' => $groupField + [
        'image' => ['field' => 'image'],
        // Column, not field.
        'alt' => [],
      ],
      'news_logo' => $imageLibraryField,
      'solution_banner' => $imageLibraryField,
      'solution_logo' => $imageLibraryField,
      'remote_video' => $groupField + ['video' => ['field' => 'media_oembed_video']],
    ];

    return $commonMap + $bundleMap[$mediaTypeId];
  }

}
