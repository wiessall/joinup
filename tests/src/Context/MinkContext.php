<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Mink\Selector\Xpath\Escaper;
use Drupal\DrupalExtension\Context\MinkContext as DrupalExtensionMinkContext;
use Drupal\joinup\Traits\JavascriptTrait;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Provides step definitions for interacting with Mink.
 */
class MinkContext extends DrupalExtensionMinkContext {

  use JavascriptTrait;

  /**
   * {@inheritdoc}
   */
  public function checkOption($option): void {
    $this->scrollNamedElementIntoView('checkbox', $option);
    parent::checkOption($option);
  }

  /**
   * {@inheritdoc}
   */
  public function uncheckOption($option): void {
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
    if ($this->hasJavascriptSupport()) {
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

  /**
   * {@inheritdoc}
   */
  public function assertRegionLinkFollow($link, $region): void {
    $region_obj = $this->getRegion($region);

    // Find the link within the region.
    $link_obj = $region_obj->findLink($link);
    if (empty($link_obj)) {
      throw new \Exception(sprintf('The link "%s" was not found in the region "%s" on the page %s', $link, $region, $this->getSession()->getCurrentUrl()));
    }
    $this->scrollElementIntoView($link_obj);
    $link_obj->click();
  }

  /**
   * Change the option of a select in the specified region.
   *
   * @Given I select :value from :field in the :region( region)
   *
   * @throws \Exception
   *   If region cannot be found.
   */
  public function selectRegionFillField(string $field, string $value, string $region): void {
    $select = $this->fixStepArgument($field);
    $option = $this->fixStepArgument($value);
    $regionObj = $this->getRegion($region);
    $regionObj->selectFieldOption($select, $option);
  }

  /**
   * Change the attachments file to field in the specified region.
   *
   * Example: And I attach the file "hi.png" to "image" in the "Modal" region.
   *
   * @Given /^(?:|I )attach the file "(?P<path>[^"]*)" to "(?P<field>(?:[^"]|\\")*)" in the "(?P<region>[^"]*)" region$/
   */
  public function attachRegionFileToField(string $field, string $path, string $region): void {
    $field = $this->fixStepArgument($field);

    if ($this->getMinkParameter('files_path')) {
      $fullPath = rtrim(realpath($this->getMinkParameter('files_path')), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path;
      if (is_file($fullPath)) {
        $path = $fullPath;
      }
    }

    $regionObj = $this->getRegion($region);
    $regionObj->attachFileToField($field, $path);
  }

}
