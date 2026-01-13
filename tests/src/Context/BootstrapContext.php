<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\UtilityTrait;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Step definitions for interacting with pages using the Bootstrap framework.
 */
class BootstrapContext extends RawDrupalContext {

  use JavascriptTrait;
  use UtilityTrait;

  /**
   * Some common screen resolutions, mapped on Bootstrap breakpoints.
   */
  protected const RESOLUTIONS = [
    // iPhone 3G.
    'extra small' => [320, 480],
    // iPhone 4.
    'small' => [640, 960],
    // Tablet.
    'medium' => [768, 1024],
    // 15" monitor.
    'large' => [1024, 768],
    // 14" laptop.
    'extra large' => [1280, 800],
    // 24" monitor.
    'extra extra large' => [1920, 1200],
  ];

  /**
   * Resizes the window to a resolution matching a Bootstrap breakpoint.
   *
   * @param string $size
   *   A human readable version of a Bootstrap breakpoint (e.g. 'extra large'
   *   instead of 'xl').
   */
  #[Given('I use a device with a(n) :size display')]
  public function changeResolution(string $size): void {
    $this->assertJavaScriptEnabledBrowser();

    [$width, $height] = self::RESOLUTIONS[$size];
    $this->getSession()->resizeWindow($width, $height, 'current');
  }

  /**
   * Expands an accordion section.
   *
   * @param string $section
   *   The accordion sections label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When there's no accordion section with the given label.
   */
  #[When('I expand the :section accordion section')]
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
   */
  #[When('I collapse the :section accordion section')]
  public function iCollapseAccordionSection(string $section): void {
    $this->toggleAccordionSection($section, FALSE);
  }

  /**
   * Toggles an accordion section by clicking its title.
   *
   * @param string $section
   *   The accordion section title.
   * @param bool $expand
   *   The action: TRUE when expanding, FALSE when collapsing.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the accordion section with the given label was not found.
   */
  protected function toggleAccordionSection(string $section, bool $expand): void {
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    $session = $this->getSession();
    $page = $session->getPage();
    if (!$button = $page->findButton($section)) {
      throw new ElementNotFoundException($session->getDriver(), 'button', 'label', $section);
    }

    if ($expand xor $button->hasClass('collapsed')) {
      $state = $expand ? 'expanded' : 'collapsed';
      throw new \Exception("The '$section' accordion section is already $state");
    }

    $button->press();
    $this->waitForCssAnimations();
  }

  /**
   * Asserts that an accordion section is active.
   *
   * @param string $section
   *   The accordion section title.
   *
   * @throws \Exception
   *   When the accordion section is not found on the page or it's not active.
   */
  #[Then('the :section accordion section should be active')]
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

  /**
   * Asserts that a Bootstrap collapse element is expanded.
   *
   * @param string $label
   *   The element label.
   */
  #[Then('the :label collapse is expanded')]
  public function assertCollapseIsExpanded(string $label): void {
    $this->collapseAssertionHelper($label, TRUE);
  }

  /**
   * Asserts that a Bootstrap collapse element is collapsed.
   *
   * @param string $label
   *   The element label.
   */
  #[Then('the :label collapse is collapsed')]
  public function assertCollapseIsCollapsed(string $label): void {
    $this->collapseAssertionHelper($label, FALSE);
  }

  /**
   * Provides a helper for Bootstrap collapse element assertions.
   *
   * @param string $label
   *   The element label.
   * @param bool $expect_expanded
   *   The expectation.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   If the collapse element is not found on the page.
   */
  protected function collapseAssertionHelper(string $label, bool $expect_expanded): void {
    $collapse = $this->findCollapseElement($label);
    $aria_expanded_value = $expect_expanded ? 'true' : 'false';
    $condition = $this->waitUntil(function () use ($collapse, $aria_expanded_value): bool {
      return $collapse->getAttribute('aria-expanded') === $aria_expanded_value;
    });
    if (!$condition) {
      if ($expect_expanded) {
        throw new ExpectationFailedException("The Bootstrap '$label' collapse element should be expanded but instead is collapsed");
      }
      throw new ExpectationFailedException("The Bootstrap '$label' collapse element should be collapsed but instead is expanded");
    }
  }

  /**
   * Finds a Bootstrap collapse element on the page.
   *
   * @param string $label
   *   The element label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The collapse element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   If the collapse element is not found on the page.
   */
  protected function findCollapseElement(string $label): NodeElement {
    $xpath = '//button[@data-bs-toggle="collapse" and contains(text(), "' . $label . '")]';
    if (!$collapse = $this->getSession()->getPage()->find('xpath', $xpath)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'Bootstrap collapse element', $label, 'label');
    }
    return $collapse;
  }

}
