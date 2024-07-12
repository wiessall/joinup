<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\BootstrapTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\KeyboardInteractionTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup_test\EventSubscriber\SearchApiFinishRequestSubscriber;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for testing searches.
 */
class JoinupSearchContext extends RawDrupalContext {

  use BootstrapTrait;
  use EntityTrait;
  use JavascriptTrait;
  use KeyboardInteractionTrait;
  use SearchTrait;
  use TraversingTrait;
  use UtilityTrait;

  /**
   * Path to the search box dropdown element.
   */
  protected const SEARCH_BOX_DROPDOWN = '.joinup-navbar button#search-box-dropdown.dropdown-toggle';

  /**
   * Enable search_api indexing on finish request.
   *
   * @BeforeScenario
   */
  public function beforeScenarioFinishIndexing(): void {
    \Drupal::state()->set(SearchApiFinishRequestSubscriber::STATE_KEY, TRUE);
  }

  /**
   * Disable search_api indexing on finish request.
   *
   * @AfterScenario
   */
  public function afterScenarioFinishIndexing(): void {
    \Drupal::state()->delete(SearchApiFinishRequestSubscriber::STATE_KEY);
  }

  /**
   * Forces the indexing of new or changed content after each step.
   *
   * @AfterStep
   */
  public function afterStepIndexing(): void {
    \Drupal::service('search_api.post_request_indexing')->destruct();
  }

  /**
   * Navigates to the search page.
   *
   * @Given I am on the search( results)( page)
   * @When I visit the search( results)( page)
   */
  public function visitSearchPage() {
    $this->visitPath('/search');
  }

  /**
   * Checks that the user is on the search page.
   *
   * The search page currently doesn't have a title so our common practice of
   * checking the page title falls short here.
   *
   * @Then I should be on the (advanced )search page
   */
  public function assertSearchPage(): void {
    $this->assertSession()->addressEquals($this->locatePath('/search'));
  }

  /**
   * Click a specific tab facet in the page.
   *
   * @param string $type
   *   The text of the content tab.
   *
   * @throws \Exception
   *   Thrown when the tab is not found in the page.
   *
   * @When I click the :type content tab
   */
  public function clickContentTypeFacet(string $type): void {
    $this->findContentTab($type)->click();
  }

  /**
   * Checks if a specific tab facet is not displayed on the page.
   *
   * @param string $type
   *   The text of the content tab.
   *
   * @throws \Exception
   *   Thrown when the tab is not found in the page.
   *
   * @Given the :type content tab is not displayed
   */
  public function assertContentTabNotDisplayed(string $type) {
    if ($this->findContentTab($type)) {
      throw new \Exception("The tab '$type' was found in the page but it should be displayed.");
    }
  }

  /**
   * Checks if a specific tab facet is displayed on the page.
   *
   * @param string $type
   *   The text of the content tab.
   *
   * @throws \Exception
   *   Thrown when the tab is not found in the page.
   *
   * @Given the :type content tab is displayed
   */
  public function assertContentTabDisplayed(string $type) {
    if (!$this->findContentTab($type)) {
      throw new \Exception("The tab '$type' was not found in the page but it should be displayed.");
    }
  }

  /**
   * Provides a helper that checks if content tab exist and returns it.
   *
   * @param string $type
   *   The text of the content tab.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The link as node element or NULL.
   */
  protected function findContentTab(string $type): ?NodeElement {
    $xpath = "//div[@data-drupal-facet-id]//span[@class='tab__text--type' and text() = '$type']/ancestor-or-self::a[@data-drupal-facet-item-id]";
    return $this->getSession()->getPage()->find('xpath', $xpath);
  }

  /**
   * Checks that the given content facet checkbox item is selected.
   *
   * @param string $bundle
   *   The text of the checkbox that should be selected.
   *
   * @throws \Exception
   *   Thrown when the facet checkbox is not selected or not present in the
   *   page.
   *
   * @Then the :bundle content checkbox item should be selected
   */
  public function assertContentFacetCheckboxSelected(string $bundle): void {
    // The following xpath searches for
    // * Any div
    // ** that has a class named facets-widget-checkbox
    // ** and contains an a element
    // *** that has a class named 'is-active'
    // *** and contains a span element
    // **** that has a class named 'facet-item__value'
    // **** and the text has the value $bundle.
    $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' facets-widget-checkbox ')][.//a[contains(concat(' ', normalize-space(@class), ' '), ' is-active ')][.//span[text()='$bundle']]]";
    $element = $this->getSession()->getPage()->find('xpath', $xpath);
    Assert::assertNotEmpty($element);
  }

  /**
   * Asserts that certain content type facet items are shown on the page.
   *
   * @param string $labels
   *   A comma-separated list of facet item labels.
   *
   * @throws \Exception
   *   Thrown when a wanted facet item is not shown in the page.
   *
   * @When I should see the following facet items :labels in this order
   */
  public function assertContentTypeFacetItemsPresent(string $labels): void {
    $labels = $this->explodeCommaSeparatedStepArgument($labels);
    $xpath = "//a[@data-drupal-facet-item-id]//span[contains(concat(' ', normalize-space(@class), ' '), ' tab__text--type ') or contains(concat(' ', normalize-space(@class), ' '), ' facet-item__value ')]";

    $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);
    $present = [];

    /** @var \Behat\Mink\Element\NodeElement $element */
    foreach ($elements as $element) {
      $present[] = $element->getText();
    }

    $present = array_map('trim', $present);
    Assert::assertSame($labels, $present);
  }

  /**
   * Asserts that certain content type facet items are not shown on the page.
   *
   * @param string $labels
   *   A comma-separated list of facet item labels.
   *
   * @throws \Exception
   *   Thrown when an unwanted facet item is shown in the page.
   *
   * @When I should not see the following facet items :labels
   */
  public function assertContentTypeFacetItemsNotPresent(string $labels): void {
    $labels = $this->explodeCommaSeparatedStepArgument($labels);

    $xpath = "//a[contains(concat(' ', normalize-space(@class), ' '), ' tab--content-type ')]//span[contains(concat(' ', normalize-space(@class), ' '), ' tab__text--lower ')]";
    $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);
    $present = [];

    /** @var \Behat\Mink\Element\NodeElement $element */
    foreach ($elements as $element) {
      $present[] = $element->getText();
    }

    $present = array_map('trim', $present);
    $found = array_intersect($labels, $present);

    if ($found) {
      throw new \Exception('Facet item(s) found, but should not: ' . implode(', ', $found));
    }
  }

  /**
   * Clicks a facet item in an inline facet.
   *
   * @param string $link
   *   The link text of the link to click.
   * @param string $facet
   *   The facet alias.
   *
   * @throws \Exception
   *   Thrown when the facet or the link inside the facet is not found.
   *
   * @When I click :link in the :facet inline facet
   */
  public function iClickAnInlineFacetItemLink(string $link, string $facet): void {
    $this->findFacetByAlias($facet)->clickLink($link);
  }

  /**
   * Asserts the list of available options in a select facet.
   *
   * @param string $select
   *   The name of the field element.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The available list of options.
   *
   * @throws \Exception
   *    Throws an exception when the select is not found or options are not
   *    identical.
   */
  public function assertSelectFacetOptionsAsList($select, TableNode $table) {
    $element = $this->findFacetByAlias($select);
    $this->assertSelectAvailableOptions($element, $table);
  }

  /**
   * Checks a checkbox link in a facet.
   *
   * @param string $option
   *   The label of the checkbox.
   * @param string $facet_type
   *   The label of the facet.
   *
   * @throws \Exception
   *   Thrown when the checkbox is not found.
   *
   * @Given I check the :option checkbox from the :facet_type facet
   */
  public function checkCheckboxFacet(string $option, string $facet_type): void {
    $facet = $this->findFacetByAlias($facet_type);
    /** @var \Behat\Mink\Element\NodeElement[] $node_elements */
    $node_elements = $facet->findAll('css', 'li.facet-item label');
    foreach ($node_elements as $node_element) {
      if ($node_element->getText() === $option) {
        $facet_id = $facet->getAttribute('data-drupal-facet-id');
        $this->scrollCssSelectedElementIntoView('[data-drupal-facet-id="' . $facet_id . '"]');
        $node_element->click();
        return;
      }
    }

    throw new \Exception("The option '{$option}' was not found in the '{$facet_type}' facet.");
  }

  /**
   * Asserts the autocomplete items from search.
   *
   * This function asserts also the order of the items.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A list of items to be present.
   *
   * @throws \Exception
   *    Thrown when the suggestions are not present in the page.
   *
   * @Then I should see the (following )search suggestion(s):
   */
  public function iShouldSeeTheSuggestions(TableNode $table): void {
    $session = $this->getSession();
    $element = $this->getSearchBarElement();
    $session->getDriver()->keyDown($element->getXpath(), '', NULL);
    $session->wait(1000);
    $allResults = $this->getSession()->getPage()->findAll('css', 'ul.ui-autocomplete a');
    $found = array_map(function ($item) {
      /** @var \Behat\Mink\Element\NodeElement $item */
      return $item->getText();
    }, $allResults);
    $missing_suggestions = array_diff($table->getColumn(0), $found);
    if (!empty($missing_suggestions)) {
      $missing_suggestions = implode(', ', $missing_suggestions);
      throw new \RuntimeException("The expected suggestions '$missing_suggestions' were not found in autocomplete.");
    }
  }

  /**
   * Asserts the number of topics in a given card.
   *
   * @param int $number
   *   The expected number of topics.
   * @param string $heading
   *   The heading in the card.
   *
   * @Given there should be :number topic(s) in the :heading card
   */
  public function assertNumberOfTopicsInCards(int $number, string $heading): void {
    $card = $this->getTileByHeading($heading);
    $topics = $card->findAll('css', '.badge');
    foreach ($topics as $index => $topic) {
      // Remove the badges that explain the extra amount of topics.
      if (preg_match('/^\+\d{1,2} topics?$/', $topic->getText()) === 1) {
        unset($topics[$index]);
      }
    }
    Assert::assertSame(count($topics), $number);
  }

  /**
   * Asserts that a facet exists in the page.
   *
   * @param string $facet
   *   The facet name.
   *
   * @Then I should see the :facet facet
   */
  public function iShouldSeeTheFacet(string $facet): void {
    $facet_element = $this->findFacetByAlias($facet);
    Assert::assertNotEmpty($facet_element->getText(), "The facet '{$facet}' was not found in the page.");
  }

  /**
   * Asserts that a facet does not exist in the page.
   *
   * @param string $facet
   *   The facet name.
   *
   * @Then I should not see the :facet facet
   */
  public function iShouldNotSeeTheFacet(string $facet): void {
    $element = $this->findFacetByAlias($facet, NULL, '*', FALSE);
    // The facet might be present but with no options.
    Assert::assertTrue(empty($element) || empty($element->getText()), "The facet '{$facet}' was found in the page.");
  }

  /**
   * Opens the dropdown for the given facet element.
   *
   * @param \Behat\Mink\Element\NodeElement $facet
   *   The facet element, as returned by `TraversingTrait::findFacetByAlias()`.
   */
  protected function openFacetDropdown(NodeElement $facet): void {
    // Only needed when running in a JS enabled browser.
    if ($this->hasJavascriptSupport()) {
      // Only open the dropdown if it is not already open.
      if (!$this->isFacetDropdownVisible($facet)) {
        // Click the arrow down button and wait for the animation to finish.
        $facet->find('css', '.filter__icon.icon--arrow-down')->click();
        $this->waitUntil(function () use ($facet): bool {
          $is_visible = (bool) $facet->find('css', 'div.mdl-menu__container.is-visible');
          $is_animating = (bool) $facet->find('css', 'ul.filter__menu.is-animating');
          return $is_visible && !$is_animating;
        });
      }
    }
  }

  /**
   * Checks whether the dropdown for the given facet element is visible.
   *
   * @param \Behat\Mink\Element\NodeElement $facet
   *   The facet element, as returned by `TraversingTrait::findFacetByAlias()`.
   *
   * @return bool
   *   TRUE if the dropdown is visible.
   */
  protected function isFacetDropdownVisible(NodeElement $facet): bool {
    // There is no point calling this method when JS is not enabled, since in
    // this case it will always return FALSE.
    $this->assertJavaScriptEnabledBrowser();
    return (bool) $facet->find('css', 'div.mdl-menu__container.is-visible');
  }

  /**
   * Asserts that an inline facet has a certain text (value) set as active.
   *
   * @param string $text
   *   The text that should be in the active element of the facet.
   * @param string $facet
   *   The inline facet to test.
   *
   * @throws \Exception
   *   Thrown when the active items element is not found.
   *
   * @Then :text should be selected in the :facet inline facet
   */
  public function assertInlineFacetActiveText(string $text, string $facet): void {
    $element = $this->findFacetByAlias($facet);

    $active = $element->find('css', '.filter__term');
    if (!$active) {
      throw new \Exception("Cannot find active items on the facet '$facet'.");
    }

    $active_text = trim($active->getText());
    if ($text !== $active_text) {
      throw new \Exception("Expected active elements to be '$text', but found '$active_text'.");
    }
  }

  /**
   * Asserts the inactive items on an inline facet.
   *
   * This function asserts also the order of the items.
   *
   * @param string $facet
   *   The facet alias.
   * @param \Behat\Gherkin\Node\TableNode $values
   *   A list of items to be present.
   *
   * @throws \Exception
   *   Thrown when the facet is not found in the page.
   *
   * @Then the :facet inline facet should allow selecting the following values:
   */
  public function assertInlineFacetInactiveItems(string $facet, TableNode $values): void {
    $element = $this->findFacetByAlias($facet);
    $found = array_map(function ($item) {
      /** @var \Behat\Mink\Element\NodeElement $item */
      return $item->getText();
    }, $element->findAll('css', 'li.facet-item'));

    Assert::assertEquals($values->getColumn(0), $found, "The '{$facet}' values mismatch the expected ones.");
  }

  /**
   * Asserts the items in a checkbox widget facet.
   *
   * This function asserts also the order of the items.
   *
   * @param string $facet
   *   The facet alias.
   * @param string $values
   *   A comma-separated list of items to be present.
   *
   * @throws \Exception
   *   Thrown when the facet is not found in the page.
   *
   * @Then the :facet checkbox facet should allow selecting the following values :values
   */
  public function assertCheckboxFacetItems(string $facet, string $values): void {
    $element = $this->findFacetByAlias($facet);

    $css = $this->hasJavascriptSupport() ? 'li.facet-item label' : 'li.facet-item';
    $found = array_map(function (NodeElement $item) {
      $text = $item->getText();
      // If a value is selected, it is prefixed with a '(-) ' in the text. Clean
      // that out so that it is more readable.
      return preg_replace('/^\(-\) /', '', $text);
    }, $element->findAll('css', $css));

    $values = $this->explodeCommaSeparatedStepArgument($values);
    Assert::assertEquals(
      $values,
      $found,
      sprintf(
        "The '%s' values mismatch the expected ones.\nExpected values: %s\nActual values: %s",
        $facet,
        implode(', ', $values),
        implode(', ', $found),
      ),
    );
  }

  /**
   * Enters keywords in the search bar.
   *
   * Note that this will just enter the keywords, it will not launch the search.
   *
   * @param string $keywords
   *   A list of words to search.
   *
   * @throws \Exception
   *   Thrown when the header search bar is not found.
   *
   * @When I enter :keywords in the search bar
   */
  public function enterSearchKeywords(string $keywords): void {
    $element = $this->getSearchBarElement();
    $element->setValue($keywords);
  }

  /**
   * Launches a search.
   *
   * @param string $keywords
   *   A list of words to search.
   *
   * @throws \Exception
   *   Thrown when the header search bar is not found.
   *
   * @When I enter :keywords in the search bar and press enter
   */
  public function launchSearchFromHeader(string $keywords): void {
    if ($this->hasJavascriptSupport()) {
      $element = $this->getSearchBarElement();
      $element->setValue($keywords);
      $this->submitSearch();
    }
    else {
      // The header search form doesn't have a submit button but is submitted by
      // pressing the Enter key. Sending keys is not supported by GoutteDriver,
      // so we cannot fake the pressing of the Enter key. We will simply
      // redirect to the search page.
      $this->visitPath('/');
      $page = $this->getSession()->getPage();
      $page->fillField('keys', $keywords);
      $page->pressButton("Search site-wide");
      $this->assertCurrentUrl($this->locatePath('search?keys=' . $keywords . '&sort_by=relevance'));
    }
  }

  /**
   * Submits the search form.
   *
   * For some reason sending a return key press to the form input field does not
   * result in a form submission in Selenium, even though it works when
   * performed manually in the browser. This step forces a form submission by
   * calling the Selenium method directly.
   *
   * @throws \Exception
   *   Thrown when the browser doesn't support Javascript or when the search
   *   field is not found.
   *
   * @When I submit the search by pressing enter
   */
  public function submitSearch(): void {
    $this->assertJavaScriptEnabledBrowser();
    $element = $this->getSearchBarElement();
    $element->submit();
  }

  /**
   * Focuses on the search input.
   *
   * @When I focus on the search input
   */
  public function focusOnSearchInput() {
    // One can only open the search bar if JavaScript is enabled.
    $this->assertJavaScriptEnabledBrowser();
    $element = $this->getSearchBarElement();
    $element->focus();
  }

  /**
   * Returns the search bar as a Mink page element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The search bar.
   */
  protected function getSearchBarElement(): NodeElement {
    $elements = $this->getElementsMatchingElementAlias('search bar');

    Assert::assertNotEmpty($elements, 'Could not find the search field in the page.');
    Assert::assertCount(1, $elements, 'There are multiple search fields on the page.');

    return reset($elements);
  }

  /**
   * Asserts that certain facet summary items are shown on the page.
   *
   * @param string $labels
   *   A comma-separated list of facet item labels.
   *
   * @throws \Exception
   *   Thrown when a wanted facet item is not shown in the page.
   *
   * @When I should see the following facet summary :labels
   */
  public function assertFacetSummary(string $labels): void {
    $present_labels = $this->explodeCommaSeparatedStepArgument($labels);
    $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' block-facets-summary-blocksearch-facets-summary ')]//li[contains(concat(' ', normalize-space(@class), ' '), ' facet-summary-item--facet ')]";
    $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);
    $present = [];

    /** @var \Behat\Mink\Element\NodeElement $element */
    foreach ($elements as $element) {
      $present[] = $element->getText();
    }

    $present = array_map('trim', $present);
    $present_labels = array_map('trim', $present_labels);
    Assert::assertEquals($present_labels, $present);
  }

  /**
   * Removes facet summary item that are on the page.
   *
   * @param string $label
   *   A facet summary item label.
   *
   * @throws \Exception
   *   Thrown when a wanted facet item is not shown in the page.
   *
   * @When I remove the following facet summary :label
   */
  public function removeFacetSummary(string $label): void {
    $css = "div.block-facets-summary-blocksearch-facets-summary li.facet-summary-item--facet a:contains('{$label}')";
    $element = $this->getSession()->getPage()->find('css', $css);

    if (empty($element)) {
      throw new \Exception("The $label facet summary item was not found in the page.");
    }
    $remove = $element->find('css', '.js-facet-deactivate');
    $remove->click();
  }

  /**
   * Asserts that the expected number of summaries are shown.
   *
   * @param string|null $number
   *   The expected number of summaries. This is a string rather than an integer
   *   because step definitions are represented in text.
   *
   * @throws \Exception
   *    Thrown when the region is not found.
   *
   * @Then the page should contain :number facet(s) summary/summaries
   * @Then the page should not contain any facet summary
   */
  public function assertFacetsSummaryCount(?string $number = NULL): void {
    $number = $number === 'one' ? 1 : (int) $number;
    $xpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' block-facets-summary-blocksearch-facets-summary ')]//li[contains(concat(' ', normalize-space(@class), ' '), ' facet-summary-item--facet ')]";
    $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);
    Assert::assertCount($number, $elements);
  }

  /**
   * Checks that the given labels are found in the correct order.
   *
   * Table format:
   * | Search         |
   * | Search Results |
   *
   * @Then I( should) see the( following) search header in the correct order:
   */
  public function assertOrderedTilesPresent(TableNode $titles_table) {
    $regionObj = $this->getSession()->getPage();

    $result = [];
    foreach ($regionObj->findAll('css', '.view-header div.form-item-results-count, .view-header div label') as $element) {
      $title = $element->getText();
      $result[$title] = $element;
    }
    $headings_in_page = array_keys($result);
    $headings_expected = $titles_table->getColumn(0);
    Assert::assertEquals($headings_expected, $headings_in_page, 'The expected search header were not found or were not in the proper order.');
  }

  /**
   * Checks if search bar appears in navbar.
   *
   * @throws \Exception
   *   Thrown when search bar item do not appear in navbar.
   *
   * @Then I should see search bar in navbar
   */
  public function assertSearchExposedFilterNavbar(): void {
    $getAllInput = $this->findAllSearchBoxes();
    if (count($getAllInput) < 1) {
      throw new \Exception('Search bar not found in navbar.');
    }
  }

  /**
   * Checks if search bar icon appears in navbar.
   *
   * @throws \Exception
   *   Thrown when search bar icon do not appear in navbar.
   *
   * @Then I should see search bar icon in navbar
   */
  public function assertSearchIconNavbar(): void {
    $getAllInput = $this->getSession()->getPage()->findAll('css', self::SEARCH_BOX_DROPDOWN);
    if (count($getAllInput) < 1) {
      throw new \Exception('Search bar icon not found in navbar.');
    }
  }

  /**
   * Checks if search bar icon does not appear in navbar.
   *
   * @throws \Exception
   *   Thrown when search bar icon appears in navbar.
   *
   * @Then I should not see search bar icon in navbar
   */
  public function failSearchIconNavbar(): void {
    $getAllInput = $this->getSession()->getPage()->findAll('css', self::SEARCH_BOX_DROPDOWN);

    if (count($getAllInput) > 0) {
      throw new \Exception('Search bar icon found in navbar.');
    }
  }

  /**
   * Checks if search bar appears in homepage's header.
   *
   * @throws \Exception
   *   Thrown when search bar does not appear in homepage's header.
   *
   * @Then I should see the search bar in homepage's header
   */
  public function assertSearchBarHeader(): void {
    $getAllInput = $this->getSession()->getPage()->findAll('css', '.region-featured .search-bar .search-bar__input');

    if (count($getAllInput) < 1) {
      throw new \Exception('Search bar not found in homepage header.');
    }
  }

  /**
   * Checks if search bar appears in navbar.
   *
   * @throws \Exception
   *   Thrown when search bar item do not appear in navbar.
   *
   * @Then I should toggle search bar in navbar
   */
  public function assertSearchToggleIconNavbar(): void {
    // Non-JS browsers are directly interacting with the HTML, so we consider
    // the account menu to be already open.
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    // Depending on the screen width, a different dropdown item may be visible.
    /** @var \Behat\Mink\Element\NodeElement[] $elements */
    $elements = $this->getSession()->getPage()->findAll('css', '#main-navbar .dropdown-search');
    foreach ($elements as $element) {
      if ($element->isVisible()) {
        $this->openBootstrapDropdown($element);
        return;
      }
    }
    throw new \RuntimeException("'Toggle search box' is not visible and cannot be opened.");
  }

  /**
   * Show more options in a facet.
   *
   * @param string $facet_type
   *   The label of the facet.
   *
   * @throws \Exception
   *   Thrown when the checkbox is not found.
   *
   * @Given I click show more from the :facet_type facet
   */
  public function showMoreFacet(string $facet_type): void {
    $facet = $this->findFacetByAlias($facet_type);
    $this->scrollElementIntoView($facet);
    $parent_facet = $facet->getParent();
    if (!$link = $parent_facet->findLink('Show more')) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'Link', 'label', 'Show more');
    }

    $link->click();
  }

  /**
   * Sets the group content listing archived option.
   *
   * @param string $label
   *   The group label.
   * @param string $bundle
   *   The group bundle.
   * @param string $option
   *   The option to set.
   *
   * @When the :label :bundle is configured to show :option community content( in the overview)
   */
  public function setGroupContentArchivedOption(string $label, string $bundle, string $option): void {
    $option = strtolower($option);
    $options = ['all', 'archived', 'unarchived'];
    Assert::assertTrue(in_array($option, $options), sprintf('The option should be one of: %s', implode(', ', $options)));
    $entity = $this->getEntityByLabel('rdf_entity', $label, $bundle);
    $field_name = $bundle === 'collection' ? 'field_collection_content' : 'field_is_content';
    $data = $entity->get($field_name)->getValue();
    if (!empty($data)) {
      $data[0]['value']['show_archived'] = 'all';
      $entity->set($field_name, $data);
      $entity->save();
    }
  }

  /**
   * Sets a facet for the group content listing.
   *
   * @param string $label
   *   The group label.
   * @param string $bundle
   *   The group bundle.
   * @param string $facet
   *   The facet to set.
   * @param string $region
   *   The facet region.
   *
   * @When the :label :bundle is configured to show the :facet facet in the :region region
   */
  public function setGroupContentFacet(string $label, string $bundle, string $facet, string $region): void {
    $facet = strtolower($facet);
    $facet = self::getFacetIdFromAlias($facet);
    $entity = $this->getEntityByLabel('rdf_entity', $label, $bundle);
    $region = strtolower($region);
    $field_name = $bundle === 'collection' ? 'field_collection_content' : 'field_is_content';
    $data = $entity->get($field_name)->getValue();
    if (!empty($data)) {
      $data[0]['value']['fields'][$facet] = [
        'region' => $region,
        'weight' => 0,
      ];
      $entity->set($field_name, $data);
      $entity->save();
    }
  }

  /**
   * Asserts items on a tab facet.
   *
   * This function asserts also the order of the items.
   *
   * @param string $facet
   *   The facet alias.
   * @param \Behat\Gherkin\Node\TableNode $values
   *   A list of items to be present.
   *
   * @throws \Exception
   *   Thrown when the facet is not found in the page.
   *
   * @Then the :facet tabs facet should allow selecting the following values:
   */
  public function assertFacetTabsItems(string $facet, TableNode $values): void {
    $element = $this->findFacetByAlias($facet);
    $found = array_map(function ($item) {
      /** @var \Behat\Mink\Element\NodeElement $item */
      return $item->getText();
    }, $element->findAll('css', 'a.tab'));

    Assert::assertEquals($values->getColumn(0), $found, "The '{$facet}' values mismatch the expected ones.");
  }

}
