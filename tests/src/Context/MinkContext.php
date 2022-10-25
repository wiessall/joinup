<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Mink\Selector\Xpath\Escaper;
use Drupal\DrupalExtension\Context\MinkContext as DrupalExtensionMinkContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\MaterialDesignTrait;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Provides step definitions for interacting with Mink.
 */
class MinkContext extends DrupalExtensionMinkContext {

  use JavascriptTrait;
  use MaterialDesignTrait;

  /**
   * {@inheritdoc}
   */
  public function checkOption($option): void {
    // @todo Remove this if (...) block in ISAICP-7138.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7138
    if ($this->usesMaterialDesignLite()) {
      $this->checkMaterialDesignField($option, $this->getSession()->getPage());
      return;
    }

    $this->scrollNamedElementIntoView('checkbox', $option);
    parent::checkOption($option);
  }

  /**
   * {@inheritdoc}
   */
  public function uncheckOption($option): void {
    // @todo Remove this if (...) block in ISAICP-7138.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7138
    if ($this->usesMaterialDesignLite()) {
      $this->uncheckMaterialDesignField($option, $this->getSession()->getPage());
      return;
    }

    $this->scrollNamedElementIntoView('checkbox', $option);
    parent::uncheckOption($option);
  }

  /**
   * {@inheritdoc}
   *
   * @Then the :radio radio button should be selected
   */
  public function assertCheckboxChecked($radio) {
    // We're just adding a step definition, not changing the actual code. Trick
    // PHP_CodeSniffer so it doesn't throw 'Useless method detected.'.
    // @codingStandardsIgnoreLine
    $tricksy = TRUE;
    parent::assertCheckboxChecked($radio);
  }

  /**
   * {@inheritdoc}
   */
  public function assertPageNotContainsText($text) {
    // When running in a JS enabled browser, check that the text is not visually
    // visible.
    if ($this->browserSupportsJavaScript()) {
      $xpath = '//*[text()[contains(.,"' . $text . '")]]';
      foreach ($this->getSession()->getPage()->findAll('xpath', $xpath) as $element) {
        if ($element->isVisible()) {
          throw new ExpectationFailedException("Element with text '$text' is visually visible.");
        }
      }
    }
    // Default to the standard behavior of checking that the response body
    // doesn't contain the text.
    else {
      parent::assertPageNotContainsText($text);
    }
  }

  /**
   * {@inheritdoc}
   *
   * The parent method already waits for animations to finish. We want to add
   * a friendlier name to explain in the tests that we are waiting for
   * animations and not for AJAX operations in that step.
   *
   * @Given I wait for animations to finish
   */
  public function iWaitForAjaxToFinish($event = NULL) {
    // We're just adding a step definition, not changing the actual code. Trick
    // PHP_CodeSniffer so it doesn't throw 'Useless method detected.'.
    // @codingStandardsIgnoreLine
    $tricksy = TRUE;
    parent::iWaitForAjaxToFinish($event);
  }

  /**
   * Wait for the given amount of seconds.
   *
   * @param string $seconds
   *   The seconds to wait.
   *
   * @Given I wait for :seconds second(s)
   */
  public function iWaitForSeconds(string $seconds) {
    $this->getSession()->wait($seconds * 1000);
  }

  /**
   * {@inheritdoc}
   */
  public function clickLink($link): void {
    // Overriding the base method for Javascript tests.
    $this->scrollNamedElementIntoView('link', $link);
    parent::clickLink($link);
  }

  /**
   * {@inheritdoc}
   */
  public function pressButton($button): void {
    // Overriding the base method for Javascript tests.
    $this->scrollNamedElementIntoView('button', $button);
    parent::pressButton($button);
  }

  /**
   * {@inheritdoc}
   */
  public function assertLinkVisible($link): void {
    $this->scrollNamedElementIntoView('link', $link);
    parent::assertLinkVisible($link);
  }

  /**
   * {@inheritdoc}
   */
  public function fillField($field, $value): void {
    $this->scrollNamedElementIntoView('field', $field);
    parent::fillField($field, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function attachFileToField($field, $path): void {
    $this->scrollNamedElementIntoView('field', $field);
    parent::attachFileToField($field, $path);
  }

  /**
   * {@inheritdoc}
   */
  public function assertSelectRadioById($label, $id = ''): void {
    $page = $this->getSession()->getPage();
    if ($id) {
      $radio = $page->findById($id);
    }
    else {
      $radio = $page->find('named', [
        'radio',
        (new Escaper())->escapeLiteral($label),
      ]);
    }
    if (!$radio) {
      throw new \Exception(sprintf('The radio button with "%s" was not found on the page %s', $id ?: $label, $this->getSession()->getCurrentUrl()));
    }
    $value = $radio->getAttribute('value');
    $radio_id = $radio->getAttribute('id');
    $label_on_page = $page->find('css', "label[for='$radio_id']")->getText();
    if ($label !== $label_on_page) {
      throw new \Exception(sprintf("Button with id '%s' has label '%s' instead of '%s' on the page %s", $id, $label_on_page, $label, $this->getSession()->getCurrentUrl()));
    }

    $this->scrollElementIntoView($radio);
    $radio->selectOption($value);
  }

  /**
   * {@inheritdoc}
   */
  public function assertRegionPressButton($button, $region): void {
    $region_node = $this->getRegion($region);
    if (!$button_node = $region_node->findButton($button)) {
      throw new \Exception("The button '$button' was not found in the region '$region' on the page {$this->getSession()->getCurrentUrl()}");
    }
    $this->scrollElementIntoView($button_node);
    $region_node->pressButton($button);
  }

}
