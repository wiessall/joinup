<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;

/**
 * Step definitions for popover functionality.
 *
 * This context provides generic popover interactions that can be used
 * for any popover component.
 */
class PopoverContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Asserts that a popover is opened.
   */
  #[Then('a popover should open')]
  public function aPopoverShouldOpen(): void {
    $this->assertJavaScriptEnabledBrowser();
    $this->waitForElementVisible('region', 'Popover');
  }

}
