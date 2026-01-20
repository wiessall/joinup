<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Behat step definitions for testing the contact form.
 */
class ContactFormContext extends RawDrupalContext {

  /**
   * Navigates to the contact form.
   */
  #[When('I go to the contact form page')]
  #[When('I visit the contact form page')]
  #[When('I am on the contact form page')]
  public function visitContactForm(): void {
    $this->visitPath('contact');
  }

}
