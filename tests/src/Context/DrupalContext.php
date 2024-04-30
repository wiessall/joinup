<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\DrupalContext as DrupalExtensionDrupalContext;
use Drupal\joinup\Traits\FileTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\UserTrait;

/**
 * Provides step definitions for interacting with Drupal.
 */
class DrupalContext extends DrupalExtensionDrupalContext {

  use FileTrait;
  use JavascriptTrait;
  use UserTrait;

  /**
   * {@inheritdoc}
   */
  public function loggedIn() {
    $session = $this->getSession();
    $session->visit($this->locatePath('/'));

    // Check if the 'logged-in' class is present on the page.
    $element = $session->getPage();
    return $element->find('css', 'body.user-logged-in');
  }

  /**
   * {@inheritdoc}
   *
   * Similar to the parent method, but allows to use human readable column
   * names, and translates filenames of images in the fixtures folder for the
   * user profile pictures.
   */
  public function createUsers(TableNode $usersTable) {
    foreach ($usersTable->getHash() as $userHash) {
      $this->createUser($userHash);
    }
  }

  /**
   * Attempts to find a button in a table row containing giving text.
   *
   * This is for administrative pages such as the administer
   * content types screen found at `admin/structure/types`.
   *
   * @Given I press :button in the :rowText row
   * @Then I (should )see the :button in the :rowText row
   *
   * @throws \Exception
   */
  public function assertClickButtonInTableRow(string $link, string $rowText): void {
    $page = $this->getSession()->getPage();
    if ($button = $this->getTableRow($page, $rowText)->findButton($link)) {
      $this->scrollElementIntoView($button);
      // Press the button and return.
      $button->press();
      return;
    }
    throw new \Exception(sprintf('Found a row containing "%s", but no "%s" button on the page %s', $rowText, $link, $this->getSession()->getCurrentUrl()));
  }

}
