<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\eupl\Eupl;
use Drupal\joinup\HtmlManipulator;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup_licence\Entity\CompatibilityDocument;
use Drupal\node\Entity\Node;
use LoversOfBehat\TableExtension\Hook\Scope\AfterTableFetchScope;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions and related methods provided by the eupl module.
 */
class EuplContext extends RawDrupalContext {

  use EntityTrait;
  use JavascriptTrait;
  use TestingEntitiesTrait;

  /**
   * Creates the standard 'EUPL' collection and several dependencies.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when one of the entities could not be created, for example because
   *   it already exists.
   *
   * @BeforeScenario @eupl&&@api
   */
  public function setupEuplData(): void {
    // Create an owner.
    $owner = $this->nodeCreate((object) [
      'type' => 'owner',
      'title' => 'Owner',
    ]);

    // Create the EUPL entity.
    $collection = Node::create([
      'type' => 'collection',
      'uri' => Eupl::EUPL_COMMUNITY_ID,
      'title' => 'EUPL',
      'field_ar_state' => 'published',
      'field_ar_owner' => $owner->nid,
    ]);
    $collection->save();
    $this->entities['rdf_entity'][$collection->id()] = $collection;

    $solution = Node::create([
      'type' => 'solution',
      'collection' => $collection,
      'uri' => Eupl::JLA_SOLUTION,
      'title' => 'Joinup Licensing Assistant',
      'field_state' => 'published',
    ]);
    $solution->save();
    $this->entities['rdf_entity'][$solution->id()] = $solution;

    // The 'Joinup Licensing Assistant' standard custom page.
    $this->nodeCreate((object) [
      'nid' => 701805,
      'type' => 'custom_page',
      'uuid' => '3bee8b04-75fd-46a8-94b3-af0d8f5a4c41',
      'title' => 'JLA',
      'og_audience' => Eupl::JLA_SOLUTION,
      'field_state' => 'published',
    ]);

    // The 'Joinup Licensing Compatibility Check' standard custom page.
    $this->nodeCreate((object) [
      'nid' => 703242,
      'type' => 'custom_page',
      'uuid' => '431f631f-e973-4fae-8368-c31f346a9616',
      'title' => 'JLC',
      'og_audience' => Eupl::JLA_SOLUTION,
      'field_state' => 'published',
    ]);

    // Populate the compatibility documents.
    CompatibilityDocument::populate();
  }

  /**
   * Clicks the info icon next to the SPDX licence header.
   *
   * @param string $spdx
   *   The SPDX licence ID.
   *
   * @throws \Exception
   *   Thrown if the info icon could not be found.
   *
   * @Given I click the info icon of the :spdx licence( table cell)
   */
  public function clickLicenceInfoIcon(string $spdx): void {
    $xpath = '//td[@class and contains(concat(" ", normalize-space(@class), " "), " licence-comparer__header ")]/button[contains(., "' . $spdx . '")]';
    $page = $this->getSession()->getPage();
    $button = $page->find('xpath', $xpath);
    if (empty($button)) {
      throw new \Exception("Could not locate a table cell with the button {$spdx}.");
    }
    $button->press();

    // Wait for modal dialog to be ready.
    $page->waitFor(5, function ($page): ?NodeElement {
      // Open modal dialog has `style="display:block"`.
      return $page->find('xpath', '//div[@style and contains(concat(" ", normalize-space(@class), " "), " modal-dialog ")]//button[@aria-label="Close"]');
    });
  }

  /**
   * Closes the licence modal dialog.
   *
   * @throws \Exception
   *   Thrown if the dialog is not open or the x button is not found.
   *
   * @Given I close the licence modal dialog
   */
  public function closeLicenceModalDialog(): void {
    $xpath = '//div[@class and contains(concat(" ", normalize-space(@class), " "), " modal-dialog ")]//button[@aria-label="Close"]';
    $button = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($button)) {
      throw new \Exception("Either the dialog is not available or the 'Close' button could not be located.");
    }
    $button->press();
  }

  /**
   * Asserts the legal type categories order in the JLA licence listing item.
   *
   * @param string $spdx
   *   The SPDX title ID.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table of categories.
   *
   * @Then the licence item with the :spdx SPDX tag should include the following legal type categories:
   */
  public function assertLegalTypeTagsCategoriesAndOrder(string $spdx, TableNode $table): void {
    $expected = $table->getColumn(0);
    $xpath = '//article[@data-joinup-spdx="' . $spdx . '"]//span[contains(concat(" ", normalize-space(@class), " "), " licence-tile__label badge ")]';
    $this->assertCategoriesAndOrder($xpath, $expected);
  }

  /**
   * Asserts that filter categories in the JLA page are present and ordered.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table of categories.
   *
   * @Then I should see the (following )filter categories in the correct order:
   */
  public function assertFilterCategoriesAndOrder(TableNode $table): void {
    $expected = $table->getColumn(0);
    $xpath = '//div[contains(concat(" ", normalize-space(@class), " "), " licence-filter ")]//div[contains(concat(" ", normalize-space(@class), " "), " licence-filter__header ")] | //div[contains(concat(" ", normalize-space(@class), " "), " eif-filter ")]//div[contains(concat(" ", normalize-space(@class), " "), " eif-filter__header ")]';
    $this->assertCategoriesAndOrder($xpath, $expected);
  }

  /**
   * Asserts that the given categories are present and in the expected order.
   *
   * @param string $xpath
   *   The XPath query identifying the categories in the page.
   * @param array $expected
   *   The categories that are expected to be present in the page.
   */
  protected function assertCategoriesAndOrder(string $xpath, array $expected): void {
    $category_nodes = $this->getSession()->getPage()->findAll('xpath', $xpath);
    $actual = array_map(function (NodeElement $element) {
      return $element->getText();
    }, $category_nodes);
    Assert::assertEquals($expected, $actual);
  }

  /**
   * Adds a licence to the compare list given its SPDX ID.
   *
   * @param string $spdx_id
   *   The licence SPDX ID.
   *
   * @throws \Exception
   *   When the licence is not found on the page.
   *
   * @When I add the :spdx_id licence to the compare list
   */
  public function selectLicenceForComparison(string $spdx_id): void {
    $this->toggleLicenceForComparison(TRUE, $spdx_id);
  }

  /**
   * Removes a licence for the compare list given its SPDX ID.
   *
   * @param string $spdx_id
   *   The licence SPDX ID.
   *
   * @throws \Exception
   *   When the licence is not found on the page.
   *
   * @When I remove the :spdx_id licence from the compare list
   */
  public function unselectLicenceForComparison(string $spdx_id): void {
    $this->toggleLicenceForComparison(FALSE, $spdx_id);
  }

  /**
   * Checks or unchecks a licence for comparison.
   *
   * @param bool $compare
   *   TRUE if the licence should be added to the compare list.
   * @param string $spdx_id
   *   The licence SPDX ID.
   *
   * @throws \Exception
   *   When the licence is not found on the page.
   */
  protected function toggleLicenceForComparison(bool $compare, string $spdx_id): void {
    $checkbox = $this->findLicenceTile($spdx_id)->findField('Add to compare list');
    if ($compare) {
      $checkbox->check();
    }
    else {
      $checkbox->uncheck();
    }
  }

  /**
   * Selects a radio button for a licence compatibility check.
   *
   * @param string $spdx_id
   *   The SPDX ID of the licence to select.
   * @param string $label
   *   The label of the radio button. Can be either 'Use' or 'Distribute'.
   *
   * @throws \Exception
   *   Thrown when the radio button or licence is not found in the page.
   *
   * @When I choose :spdx_id as the :label licence
   */
  public function selectRadioButtonForLicenceCompatibilityCheck(string $spdx_id, string $label): void {
    \assert(in_array($label, ['Inbound', 'Outbound']), 'The purpose should be either "Use" or "Distribute".');
    $radio = $this->findLicenceTile($spdx_id)->findField($label);
    $radio->click();
  }

  /**
   * Presses a button in a licence tile.
   *
   * @param string $label
   *   The label of the button to press.
   * @param string $spdx_id
   *   The SPDX ID of the licence tile.
   *
   * @throws \Exception
   *   Thrown when the licence tile or button are not found in the page.
   *
   * @When I press :link in the :spdx_id licence tile
   */
  public function clickLicenceTileLink(string $label, string $spdx_id): void {
    $button = $this->findLicenceTile($spdx_id)->findButton($label);
    $button->press();
  }

  /**
   * Asserts that Compare / Check compatibility buttons are enabled or disabled.
   *
   * @param string $label
   *   The button labels. Either "Compare" or "Check compatibility".
   * @param string $status
   *   Either 'enabled' or 'disabled'.
   *
   * @throws \Exception
   *   When:
   *   - No buttons were found in page.
   *   - The expectancy is not satisfied.
   *
   * @Then the :label buttons are :status
   * @Then the :label buttons should be :status
   */
  public function assertLicenceButtonStatus(string $label, string $status): void {
    \assert(in_array($status, ['enabled', 'disabled']), 'The $status parameter should take one of the values "enabled" or "disabled" but "' . $status . '" was given.');

    $mapping = [
      'Compare' => ['tag' => 'button', 'class' => 'jla-compare'],
      'Check compatibility' => ['tag' => 'a', 'class' => 'jla-compatible'],
    ];
    \assert(array_key_exists($label, $mapping), 'The $label parameter should take one of the values "Compare" or "Check compatibility" but "' . $label . '" was given.');

    $page = $this->getSession()->getPage();
    $xpath = '//' . $mapping[$label]['tag'] . '[text()="' . $label . '" and contains(concat(" ", normalize-space(@class), " "), " ' . $mapping[$label]['class'] . ' ")]';
    $buttons = $page->findAll('xpath', $xpath);
    if (empty($buttons)) {
      throw new \Exception("No '$label' buttons found in the page.");
    }
    $method = $mapping[$label]['tag'] === 'button' ? 'hasAttribute' : 'hasClass';

    /** @var \Behat\Mink\Element\NodeElement[] $buttons */
    foreach ($buttons as $button) {
      if ($status === 'disabled' && !$button->{$method}('disabled')) {
        throw new \Exception("'$label' buttons should be disabled but are enabled.");
      }
      elseif ($status === 'enabled' && $button->{$method}('disabled')) {
        throw new \Exception("'$label' buttons should be enabled but are disabled.");
      }
    }
  }

  /**
   * Asserts that the given licence can/cannot be selected for the compare list.
   *
   * @param string $spdx_id
   *   The licence SPDX ID.
   * @param string $can
   *   Either 'can' or 'cannot'.
   *
   * @throws \Exception
   *   When the licence tile is not found on the page.
   *
   * @Then the :spdx_id :can be added to the compare list
   */
  public function assertLicenceCanBeCompared(string $spdx_id, string $can): void {
    \assert(in_array($can, ['can', 'cannot']), 'The $can parameter should take one of the values "can" or "cannot" but "' . $can . '" was given.');

    $checkbox = $this->findLicenceTile($spdx_id)->findField('Add to compare list');

    if ($can === 'can' && $checkbox->hasAttribute('disabled')) {
      throw new \Exception("The licence '$spdx_id' should be allowed to be compared but is not.");
    }
    elseif ($can === 'cannot' && !$checkbox->hasAttribute('disabled')) {
      throw new \Exception("The licence '$spdx_id' shouldn't be allowed to be compared but it is.");
    }
  }

  /**
   * Asserts that the given licence is/is not selected.
   *
   * @param string $spdx_id
   *   The licence SPDX ID.
   * @param string $checked
   *   Either 'checked' or 'unchecked'.
   *
   * @throws \Exception
   *   When the licence tile is not found on the page.
   *
   * @Then the :spdx_id licence should be :checked
   */
  public function assertLicenceChecked(string $spdx_id, string $checked): void {
    \assert(in_array($checked, ['checked', 'unchecked']), 'The $checked parameter should take one of the values "checked" or "unchecked" but "' . $checked . '" was given.');

    $checkbox = $this->findLicenceTile($spdx_id)->findField('Add to compare list');

    if ($checked === 'checked' && !$checkbox->hasAttribute('checked')) {
      throw new \Exception("The licence '$spdx_id' should be checked but is not.");
    }
    elseif ($checked === 'unchecked' && $checkbox->hasAttribute('checked')) {
      throw new \Exception("The licence '$spdx_id' should not be checked but it is.");
    }
  }

  /**
   * Checks the licences in the table for compatibility.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table with columns 'use', 'redistribute as' and 'document ID'.
   *
   * @Then the following combination of licences should be described in the compatibility document:
   */
  public function assertLicenceCompatibility(TableNode $table): void {
    /** @var \Drupal\joinup_licence\JoinupLicenceCompatibilityRulePluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.joinup_licence_compatibility_rule');
    foreach ($table->getColumnsHash() as $test_case) {
      $use_label = $test_case['inbound'];
      $redistribute_as_label = $test_case['outbound'];
      $expected_result = $test_case['document ID'];

      /** @var \Drupal\joinup_licence\Entity\LicenceInterface $inbound_licence */
      $inbound_licence = $this->getEntityByLabel('node', $use_label, 'licence');
      /** @var \Drupal\joinup_licence\Entity\LicenceInterface $outbound_licence */
      $outbound_licence = $this->getEntityByLabel('node', $redistribute_as_label, 'licence');

      $result = $plugin_manager->getCompatibilityDocumentId($inbound_licence, $outbound_licence);

      // Check that the returned document ID matches the expected ID.
      if ($expected_result !== $result) {
        throw new \Exception("Licences $use_label and $redistribute_as_label are expected to match the $expected_result rule but they match $result.");
      }
    }
  }

  /**
   * Creates compatibility documents using the data provided in a table.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A table with columns 'id' and 'description'.
   *
   * @Given compatibility documents:
   */
  public function createCompatibilityDocuments(TableNode $table): void {
    $this->cleanCompatibilityDocuments();
    foreach ($table->getColumnsHash() as $document) {
      CompatibilityDocument::create([
        'id' => $document['id'],
        'description' => $document['description'],
      ])->save();
    }
  }

  /**
   * Finds a licence tile on the page given a licence SPDX ID.
   *
   * @param string $spdx_id
   *   The licence SPDX ID.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The licence tile.
   *
   * @throws \Exception
   *   When the licence tile is not found on the page.
   */
  protected function findLicenceTile(string $spdx_id): NodeElement {
    if ($licence = $this->getSession()->getPage()->find('css', 'article[data-joinup-spdx="' . $spdx_id . '"]')) {
      return $licence;
    }
    throw new \Exception("Can't find the '$spdx_id' licence on the page.");
  }

  /**
   * Clears the content created for the purpose of this test.
   *
   * @AfterScenario @eupl&&@api
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function cleanEuplData(): void {
    $this->cleanCompatibilityDocuments();
  }

  /**
   * Cleans up the compatibility documents.
   *
   * @Then all compatibility documents are cleaned up
   */
  public function cleanCompatibilityDocuments(): void {
    $compatibility_document_storage = \Drupal::entityTypeManager()->getStorage('compatibility_document');
    $compatibility_document_storage->delete($compatibility_document_storage->loadMultiple());
  }

  /**
   * Simplifies the table to make the Behat assert easier.
   *
   * Table alters:
   * - Header cells contain nested markup. We're only interested in the header
   *   cell label for the purpose of comparison.
   * - There's no way to identify the checked cells in tests as the licence
   *   comparer table cells contain hidden text. Visually, a human is able to
   *   identify them as they are CSS styled. For the purpose of the test we
   *   insert an 'x' character so that the test is able to identify those cells.
   * - Visually empty cells still contain hidden text. Fully empty those cells.
   *
   * @param \LoversOfBehat\TableExtension\Hook\Scope\AfterTableFetchScope $scope
   *   The "after table fetch" scope object.
   *
   * @AfterTableFetch
   */
  public static function markLicenceComparerCheckedCells(AfterTableFetchScope $scope): void {
    $html_manipulator = new HtmlManipulator($scope->getHtml());

    if (!$html_manipulator->filter('table[data-drupal-selector="licence-comparer"]')->getNode(0)) {
      // We're only interested in the licence comparer table.
      return;
    }

    /** @var \DOMNode $td */
    foreach ($html_manipulator->filter('td.licence-comparer__header') as $td) {
      // Reduce header cell content to SPDX ID for easy comparison.
      if ($td->textContent) {
        /** @var \DOMElement $button */
        $button = $td->getElementsByTagName('button')[0];
        $label = trim($button->nodeValue);
        $td->textContent = $label;
      }
    }

    foreach ($html_manipulator->filter('td.licence-comparer__cell-on') as $td) {
      // Insert an 'x' character the licence comparer table checked cells so
      // that the tests are able to identify them.
      // @see tests/features/communities/eupl/jla.feature
      $td->textContent = 'x';
    }
    foreach ($html_manipulator->filter('td.licence-comparer__cell-off') as $td) {
      // Remove hidden markup from empty cells.
      $td->textContent = '';
    }

    $scope->setHtml($html_manipulator->html());
  }

}
