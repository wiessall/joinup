<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Debug related step definitions.
 */
class DebugContext extends RawDrupalContext {

  /**
   * Prints system message from the page for debugging.
   *
   * @Then /^print messages$/
   */
  public function printMessages(): void {
    $selector = $this->getDrupalSelector('message_selector');
    /** @var \Behat\Mink\Element\NodeElement $message */
    foreach ($this->getSession()->getPage()->findAll("css", $selector) as $message) {
      echo $message->getText() . "\n\n";
    }
  }

}
