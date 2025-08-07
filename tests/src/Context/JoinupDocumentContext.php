<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Behat step definitions for testing documents.
 */
class JoinupDocumentContext extends RawDrupalContext {

  use FileTrait;
  use EntityTrait;
  use TraversingTrait;

  /**
   * Handles the file field for document nodes.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   An object containing the entity properties and fields that are to be used
   *   for creating the node as properties on the object.
   *
   * @BeforeNodeCreate
   *
   * @throws \Exception
   *   Thrown when an attached file cannot be found or cannot be saved in the
   *   Drupal public files folder.
   */
  public static function massageDocumentFieldsBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();

    if ($node->type !== 'document') {
      return;
    }

    if (!empty($node->resources)) {
      $type = $node->{'file type'} ?? 'remote';
      if ($type != 'remote') {
        // If the file is local we want to copy it from the fixtures into the
        // file system and register it in the DocumentSubContext so it can be
        // cleaned up after the scenario ends. Perform a small dance to get
        // access to the context class from inside this static callback.
        /** @var \Behat\Behat\Context\Environment\InitializedContextEnvironment $environment */
        $environment = $scope->getEnvironment();
        /** @var \Drupal\joinup\Context\JoinupDocumentContext $context */
        $context = $environment->getContext(self::class);
        $file_id = $context->createFile($node->resources)->id();
        $paragraph = Paragraph::create([
          'type' => 'resource_file',
          'resource_file' => [
            [
              'target_id' => $file_id,
            ],
          ],
        ]);
      }
      else {
        $paragraph = Paragraph::create([
          'type' => 'resource_link',
          'remote_file_url' => $node->resources,
        ]);

      }
      $paragraph->save();
      $node->{"resources:target_id"} = $paragraph->id();
      $node->{"resources:target_revision_id"} = $paragraph->getRevisionId();
      unset($node->resources);

      unset($node->{'file type'});
    }

    // The document type is a required field and in normal usage a document
    // cannot be created without it. If the scenario doesn't specify a type,
    // default to 'document'.
    $node->field_type = $node->field_type ?? 'document';
  }

}
