<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Attribute\BeforeNodeCreate;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\NodeTrait;
use Drupal\joinup\Traits\RandomGeneratorTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Behat step definitions for testing owners.
 */
class OwnerContext extends RawDrupalContext {

  use EntityTrait;
  use NodeTrait;
  use RandomGeneratorTrait;
  use SearchTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * Handles owner specific fields.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The scope object.
   */
  #[BeforeNodeCreate]
  public function massageFieldsBeforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $fake_node = $scope->getStub();
    if ($fake_node->getBundle() !== 'owner') {
      return;
    }

    if (!$fake_node->hasValue('owner_type')) {
      $fake_node->setValue('owner_type', $this->getRandomTerm('owner_type')->label());
    }

    if (!$fake_node->hasValue('country')) {
      $fake_node->setValue('country', $this->getRandomTerm('spatial')->label());
    }
  }

}
