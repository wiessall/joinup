<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AwaitTrait;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for interacting with entity browsers.
 */
class EntityBrowserContext extends RawDrupalContext {

  use AwaitTrait;

  /**
   * Presses a button to open an entity browser and switches to its iframe.
   *
   * @param string $button
   *   The button label.
   * @param string|null $region
   *   (optional) The region where the button is located.
   */
  #[When('I press :button to open the entity browser')]
  #[When('I press :button in the :region region to open the entity browser')]
  public function pressToOpenEntityBrowser(string $button, ?string $region = NULL): void {
    $page = $this->getSession()->getPage();
    if ($region) {
      $container = $page->find('region', $region);
      if (!$container) {
        throw new \Exception(sprintf('No region "%s" found on the page %s.', $region, $this->getSession()->getCurrentUrl()));
      }
    }
    else {
      $container = $page;
    }
    $container->pressButton($button);
    $iframe = $this->waitForElement('css', '.entity-browser-modal-iframe');
    $this->getSession()->switchToIFrame($iframe->getAttribute('name'));
    // Wait for the entity browser form to be fully loaded inside the iframe.
    $this->waitForElement('css', '.entity-browser-form');
  }

  /**
   * Presses a button to close the entity browser and switches back.
   *
   * @param string $button
   *   The button label.
   */
  #[When('I press :button to close the entity browser')]
  public function pressToCloseEntityBrowser(string $button): void {
    $this->getSession()->getPage()->pressButton($button);
    $this->getSession()->switchToIFrame();
    $this->waitForNoElement('css', '.entity-browser-modal-iframe');
    $this->waitForAjax();
  }

  /**
   * Selects an entity in the entity browser by checking its row checkbox.
   *
   * @param string $title
   *   The title of the entity to select.
   */
  #[When('I select :title in the entity browser')]
  public function selectEntityInBrowser(string $title): void {
    $row = $this->waitForElement('xpath', '//table//tr[contains(., "' . $title . '")]');
    $checkbox = $row->find('css', 'input[type="checkbox"]');
    Assert::assertNotNull($checkbox, sprintf('Checkbox not found in the row containing "%s".', $title));
    $checkbox->check();
  }

}
