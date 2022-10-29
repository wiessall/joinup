<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Behat step definitions for testing the accordion widget.
 */
class AccordionContext extends RawDrupalContext {

  use JavascriptTrait;
  use UtilityTrait;

  /**
   * Expands an accordion section.
   *
   * @param string $section
   *   The accordion sections label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When there's no accordion section with the given label.
   *
   * @When I expand the :section accordion section
   */
  public function iExpandAccordionSection(string $section): void {
    $this->toggleAccordionSection($section, TRUE);
  }

  /**
   * Collapses an accordion section.
   *
   * @param string $section
   *   The accordion sections label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When there's no accordion section with the given label.
   *
   * @When I collapse the :section accordion section
   */
  public function iCollapseAccordionSection(string $section): void {
    $this->toggleAccordionSection($section, FALSE);
  }

  /**
   * Toggles an accordion section by clicking its title.
   *
   * @param string $section
   *   The accordion section title.
   * @param bool $expand
   *   The action: TRUE when expanding, FALS when collapsing.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the accordion section with the given label was not found.
   */
  protected function toggleAccordionSection(string $section, bool $expand): void {
    $session = $this->getSession();
    $page = $session->getPage();
    if (!$button = $page->findButton($section)) {
      throw new ElementNotFoundException($session->getDriver(), 'button', 'label', $section);
    }
    $is_collapsed = $button->hasClass('collapsed');
    if (($expand && !$is_collapsed) || (!$expand && $is_collapsed)) {
      $state = $expand ? 'expanded' : 'collapsed';
      throw new \Exception("The '$section' accordion section is already $state");
    }
    $this->scrollElementIntoView($button);
    $button->press();
    if ($this->hasJavascriptSupport()) {
      $this->waitUntil(function () use ($button, $expand): bool {
        $is_collapsed = $button->hasClass('collapsed');
        return ($expand && !$is_collapsed) || (!$expand && $is_collapsed);
      });
    }
  }

  /**
   * Asserts that an accordion section is active.
   *
   * @param string $section
   *   The accordion section title.
   *
   * @throws \Exception
   *   When the accordion section is not found on the page or it's not active.
   *
   * @Then the :section accordion section should be active
   */
  public function assertAccordionSectionActive(string $section): void {
    if ($this->findAccordionSection($section)->hasClass('collapsed')) {
      throw new \Exception("The accordion section '$section' is not active.");
    }
  }

  /**
   * Finds an accordion section by its title.
   *
   * @param string $section
   *   The title of the accordion secion.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The accordion section element.
   *
   * @throws \Exception
   *   Thrown when no section element is found.
   */
  protected function findAccordionSection(string $section): NodeElement {
    // Xpath to find the accordion item.
    $xpath = "//div[@class and contains(concat(' ', normalize-space(@class), ' '), ' accordion-item ')]";
    // Filter down to the accordion section containing a button with the
    // provided text.
    $xpath .= "[.//h2[@class and contains(concat(' ', normalize-space(@class), ' '), ' accordion-header ')]/button[@class and contains(concat(' ', normalize-space(@class), ' '), ' accordion-button ')]"
      . "[normalize-space(string(.)) = '$section']]";
    $element = $this->getSession()->getPage()->find('xpath', $xpath);

    if ($element === NULL) {
      throw new \Exception('Accordion item not found: ' . $section);
    }

    return $element;
  }

}
