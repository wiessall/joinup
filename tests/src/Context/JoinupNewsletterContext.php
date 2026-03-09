<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\TraversingTrait;

/**
 * Behat step definitions for testing the newsletter integration.
 */
class JoinupNewsletterContext extends RawDrupalContext {

  use TraversingTrait;

  /**
   * Checks that a newsletter subscription form is not present in the last tile.
   */
  #[Then('I should not see the newsletter subscription form in the last tile')]
  public function assertNoNewsletterSubscriptionFormPresentInFinalTile(): void {
    $tiles = $this->getTiles();
    if (empty($tiles)) {
      throw new \RuntimeException('There are no tiles on the page.');
    }

    end($tiles);
    $this->assertCondition(key($tiles) != 'Newsletter', 'The last tile should not be a newsletter subscription form but it is.');
  }

  /**
   * Checks that a newsletter subscription form is present in the last tile.
   */
  #[Then('I should see the newsletter subscription form in the last tile')]
  public function assertNewsletterSubscriptionFormPresentInFinalTile(): void {
    $tiles = $this->getTiles();
    if (empty($tiles)) {
      throw new \RuntimeException('There are no tiles on the page.');
    }

    end($tiles);
    $this->assertCondition(key($tiles) == 'Newsletter', 'The last tile should be a newsletter subscription form but it is not.');
  }

}
