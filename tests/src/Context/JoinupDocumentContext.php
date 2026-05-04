<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\TraversingTrait;

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
   * @throws \Exception
   *   Thrown when an attached file cannot be found or cannot be saved in the
   *   Drupal public files folder.
   */
  #[BeforeNodeCreate]
  public function massageDocumentFieldsBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $node = $scope->getStub();

    if ($node->getBundle() !== 'document') {
      return;
    }

    // The document type is a required field and in normal usage a document
    // cannot be created without it. If the scenario doesn't specify a type,
    // default to 'document'.
    $node->setValue('field_type', $node->getValue('field_type') ?? 'document');
  }

}
