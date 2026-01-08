<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeSuite;
use Behat\Step\Then;
use Behat\Step\When;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Chip;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\UtilityTrait;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for functionalities provided by Joinup core module.
 */
class JoinupCoreContext extends RawDrupalContext {

  use JavascriptTrait;
  use EntityTrait;
  use TraversingTrait;
  use UtilityTrait;

  /**
   * Asserts that only the expected chips are shown in the page.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table containing the expected chip labels.
   * @param string|null $region
   *   Optional region in which to locate the chips to check. If omitted all
   *   chips found in the entire page will be checked.
   *
   * @throws \Exception
   *    Thrown when the region is not found.
   */
  #[Then('the page should show( only) the( following) chip(s):')]
  #[Then('the page should show( only) the( following) chip(s) in the :region( region):')]
  public function assertChipElements(TableNode $table, ?string $region = NULL): void {
    $chips = $this->getChips($region);
    $found = array_map(function (Chip $element) {
      return $element->getText();
    }, $chips);

    $expected = $table->getColumn(0);
    Assert::assertEquals($expected, $found, "The expected chip elements don't match the ones found in the page", 0.0, 10, TRUE);
  }

  /**
   * Asserts that the expected number of chips are shown.
   *
   * @param string|null $number
   *   The expected number of chips. This is a string rather than an integer
   *   because step definitions are represented in text.
   * @param string|null $region
   *   Optional region in which to locate the chips to check. If omitted all
   *   chips found in the entire page will be checked.
   *
   * @throws \Exception
   *    Thrown when the region is not found.
   */
  #[Then('the page should contain :number chip(s)')]
  #[Then('the page should not contain any chips')]
  #[Then('the :region region should contain :number chip(s)')]
  #[Then('the :region region should not contain any chips')]
  public function assertChipCount(?string $number = NULL, ?string $region = NULL): void {
    $number = (int) $number;
    Assert::assertCount($number, $this->getChips($region));
  }

  /**
   * Clicks the remove button in a chip.
   *
   * @param string $text
   *   The text or partial text of the chip.
   *
   * @throws \Exception
   *   Thrown when either the chip or its remove button are not found.
   */
  #[When('I press the remove button on the chip :text')]
  public function clickRemoveChipButton(string $text): void {
    // Find the element that contains the given text.
    $chip = $this->getChipByText($text);
    if (!$chip) {
      throw new \Exception("The chip containing the label '$text' was not found on the page.");
    }

    // Find the related button.
    $button = $chip->getRemoveButton();
    if (!$button) {
      throw new \Exception("Couldn't find the button to remove the chip '$text'.");
    }

    $button->press();
  }

  /**
   * Returns the chips that are present in the given region.
   *
   * @param string|null $region
   *   Optional region in which to locate the chips to return. If omitted all
   *   chips found in the entire page will be returned.
   *
   * @return \Drupal\joinup\Chip[]
   *   The elements representing the chips.
   *
   * @throws \Exception
   *    Thrown when the region is not found.
   */
  protected function getChips(?string $region = NULL): array {
    // Default to the entire page if the region is omitted.
    $base_element = $region ? $this->getRegion($region) : $this->getSession()->getPage();

    $chips = [];
    // If the chips are being displayed using the `mdl-chip-input` JavaScript
    // library then they will only be visible if the browser supports JS. If the
    // browser doesn't support JavaScript then the chips are still present in
    // the DOM as invisible form input fields. We can still add them with the
    // `is_visible` flag disabled to indicate they only can be interacted with
    // in a limited way.
    $locator_visibility_mapping = [
      '.invite-form__list .badge, .navbar-search .search-badge' => TRUE,
    ];
    foreach ($locator_visibility_mapping as $locator => $is_visible) {
      foreach ($base_element->findAll('css', $locator) as $element) {
        $chips[] = new Chip($element, $is_visible);
      }
    }

    return $chips;
  }

  /**
   * Returns the chip with the given text.
   *
   * If multiple chips are present with the same text, then the first one will
   * be returned.
   *
   * @param string $text
   *   The text that should be present in the chip.
   * @param string|null $region
   *   Optional region in which to locate the chips to return. If omitted all
   *   chips found in the entire page will be returned.
   *
   * @throws \Exception
   *    Thrown when the region is not found.
   *
   * @return \Drupal\joinup\Chip|null
   *   The chip or NULL if no chip with the given text is present in the page.
   */
  protected function getChipByText(string $text, ?string $region = NULL): ?Chip {
    foreach ($this->getChips($region) as $chip) {
      if ($chip->getText() === $text) {
        return $chip;
      }
    }

    return NULL;
  }

  /**
   * Visits a taxonomy term page.
   *
   * @param string $name
   *   The taxonomy term name.
   * @param string|null $bundle
   *   (optional) Filter by bundle.
   *
   * @Given I go to the :name :bundle term page
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Thrown when the taxonomy term does not have a URL to go to.
   */
  public function visitTaxonomyTermPage(string $name, ?string $bundle = NULL): void {
    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = static::getEntityByLabel('taxonomy_term', $name, $bundle);
    $this->visitPath($term->toUrl()->toString());
  }

  /**
   * Asserts that a list of fields are disabled.
   *
   * @param string $fields
   *   A list of comma separated field labels.
   *
   * @throws \Exception
   *   Thrown when a field is not found by name or is not disabled.
   */
  #[Then('the following field(s) should be disabled :fields')]
  #[Then('the following button(s) should be disabled :fields')]
  public function givenDisabledFields(string $fields): void {
    $fields = $this->explodeDelimitedSeparatedStepArgument($fields);
    $found = [];
    foreach ($fields as $field) {
      $element = $this->findDisabledField($field);
      if (empty($element)) {
        $found[] = $field;
      }
    }

    if (!empty($found)) {
      throw new \Exception('The following fields were not found or were enabled: ' . implode(', ', $found));
    }
  }

  /**
   * Asserts that a list of fields are not disabled.
   *
   * @param string $fields
   *   A list of comma separated field labels.
   *
   * @throws \Exception
   *   Thrown when a field is not found by name or is disabled.
   */
  #[Then('the following fields should not be disabled :fields')]
  public function givenNotDisabledFields(string $fields): void {
    $fields = $this->explodeDelimitedSeparatedStepArgument($fields);
    $found = [];
    foreach ($fields as $field) {
      $element = $this->findDisabledField($field);
      if (!empty($element)) {
        $found[] = $field;
      }
    }

    if (!empty($found)) {
      throw new \Exception('The following fields were disabled: ' . implode(', ', $found));
    }
  }

  /**
   * Asserts the content type of the response from the server.
   *
   * @param string $content_type
   *   The expected content type.
   */
  #[Then('the content type of the response should be :content_type')]
  public function assertResponseContentType(string $content_type): void {
    $this->assertSession()->responseHeaderEquals('Content-Type', $content_type);
  }

  /**
   * Asserts the order of elements.
   */
  #[Then('I should see the following quick links menu items in the specified order:')]
  public function assertQuickMenuLinks(TableNode $table): void {
    $this->assertGroupMenuItems($table, 'Quick links');
  }

  /**
   * Asserts the order of elements.
   */
  #[Then('I should see the following navigation menu items in the specified order:')]
  public function assertNavigationMenuLinks(TableNode $table): void {
    $this->assertGroupMenuItems($table, 'Navigation menu');
  }

  /**
   * Asserts the order of elements.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table node.
   * @param string $region
   *   The region.
   */
  protected function assertGroupMenuItems(TableNode $table, string $region): void {
    $navigation_menu_selector = $this->getRegionSelector($region);
    $parent = $this->getSession()->getPage()->findAll('css', "$navigation_menu_selector .nav a");
    $table_hash = $table->getHash();
    Assert::assertSameSize($table_hash, $parent);
    $i = 0;
    foreach ($table_hash as $expected_element) {
      $expected_text = $expected_element['text'];
      $actual_text = $parent[$i++]->getText();
      Assert::assertSame($expected_text, $actual_text);
    }
  }

  /**
   * Searches for links matching the criteria and clicks on the last of them.
   *
   * Since the results are sequential, the last link in the results is also the
   * last instance of the link in the page matching the given criteria.
   *
   * @param string $link
   *   The link locator.
   *
   * @Given I click the last :link link
   */
  public function assertClickLastLink(string $link): void {
    $locator = ['link', $link];
    $links = $this->getSession()->getPage()->findAll('named', $locator);
    $link = end($links);
    $link->click();
  }

  /**
   * Sets the default timezone before a suite.
   *
   * @param \Behat\Testwork\Hook\Scope\BeforeSuiteScope $before_suite
   *   Contains information about the suite.
   */
  #[BeforeSuite]
  public static function setDefaultTimezone(BeforeSuiteScope $before_suite): void {
    date_default_timezone_set('UTC');
  }

  /**
   * Assert the default cache backend service.
   *
   * @param string $expected
   *   The expected cache backend service.
   *
   * @Given /^the default cache backend is set to "([^"]*)"$/
   */
  public function theDefaultCacheBackendIsSetTo(string $expected): void {
    $container = \Drupal::getContainer();
    $factory = $container->get($expected);
    Assert::assertNotNull($factory, "The service $expected does not exist. Note, that you need to pass a service ID, not a class name.");

    $actual = get_class(\Drupal::cache());
    $expected = get_class($factory->get('default'));
    Assert::assertSame($expected, $actual);
  }

  /**
   * Asserts that the given HTML list of rows exists in the page.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The table node.
   *
   * @Given /^I should see the following table rows:$/
   */
  public function iShouldSeeTheFollowingTableRows(TableNode $table): void {
    $rows = $table->getRows();
    // Get all table rows/headers in the page regardless of the table.
    $table_rows = $this->getSession()->getPage()->findAll('css', 'table tr');
    $table_rows = array_map(function ($row) {
      return $row->getText();
    }, $table_rows);

    foreach ($rows as $row) {
      Assert::assertContains(implode(' ', $row), $table_rows);
    }
  }

  /**
   * Checks whether the field of an entity is or is not empty.
   *
   * @param string $entityTypeId
   *   The entity type ID ("content" is converted automatically to "node").
   * @param string $label
   *   The label of the entity.
   * @param string $fieldName
   *   The machine name of the field what we want to check.
   * @param string $emptiness
   *   Use the string "empty" if the field is expected to be empty. Every other
   *   value means that the field must have a value.
   * @param string|null $bundle
   *   The bundle of the entity. Use NULL to not apply this restriction.
   *
   * @returns \Drupal\Core\Field\FieldItemListInterface
   *   The field instance.
   */
  #[Then('/^the (\w+) field in "([^"]+)" (\w+)? (should be empty|should not be empty)$/')]
  #[Then('/^the (?<fieldName>\w+) field in "(?<label>[^"]+)" "(?<entityTypeId>\w+)"? with type "(?<bundle>\w+)"? should be (?<emptiness>should be empty|should not be empty)$/')]
  public function assertEntityFieldEmptiness(
    string $entityTypeId,
    string $label,
    string $fieldName,
    string $emptiness,
    ?string $bundle = NULL,
  ): FieldItemListInterface {
    $bundle = is_string($bundle) ? $this->translateBundle($bundle) : NULL;
    $entity = $this->getEntityByLabel($this->translateEntityTypeAlias($entityTypeId), $label, $bundle);
    Assert::assertInstanceOf(
      FieldableEntityInterface::class,
      $entity,
      'No fieldable entity was found',
    );
    Assert::assertTrue(
      $entity->hasField($fieldName),
      sprintf("The entity has no '%s' field", $fieldName),
    );

    $field = $entity->get($fieldName);
    Assert::assertFalse(
      $field->isEmpty(),
      sprintf(
        "The '%s' field in the entity with label '%s' is empty",
        $fieldName,
        $label,
      ),
    );

    if ($emptiness === 'empty') {
      Assert::assertTrue(
        $field->isEmpty(),
        sprintf(
          "The '%s' field in the entity with label '%s' is not empty",
          $fieldName,
          $label,
        ),
      );
    }
    else {
      Assert::assertFalse(
        $field->isEmpty(),
        sprintf(
          "The '%s' field in the entity with label '%s' is empty",
          $fieldName,
          $label,
        ),
      );
    }

    return $field;
  }

  /**
   * Asserts the string value of an entity field.
   *
   * Use this assertion if there is a need for checking a property value which
   * is not revealed for the current user anywhere on the UI.
   *
   * @param string $entityTypeId
   *   The entity type ID ("content" is converted automatically to "node").
   * @param string $label
   *   The label of the entity.
   * @param string $fieldName
   *   The machine name of the field what we want to check.
   * @param string $expectedValue
   *   The expected value of the field. The "<placeholder>" string can be used
   *   to exclude dynamic content from comparison.
   * @param string|null $bundle
   *   The bundle of the entity. Use NULL to not apply this restriction.
   */
  #[Then('the string value of the :fieldName field in :label :entityTypeId should be :expectedValue')]
  #[Then('the string value of the :fieldName field in :label :entityTypeId with type :bundle should be :expectedValue')]
  public function assertFieldValueString(
    string $entityTypeId,
    string $label,
    string $fieldName,
    string $expectedValue,
    ?string $bundle = NULL,
  ): void {
    $field = $this->assertEntityFieldEmptiness($entityTypeId, $label, $fieldName, '', $bundle);
    $template = mb_strpos('<placeholder>', $expectedValue) === FALSE
      ? "Value of the '%s' field in the entity with label '%s' should be '%s', but it is '%s'"
      : "Value of the '%s' field in the entity with label '%s' should match '%s', but the actual value is '%s'";
    $expectedValuePattern = mb_ereg_replace(
      '\\\<placeholder\\\>',
      '.*',
      preg_quote($expectedValue, '/')
    );
    Assert::assertMatchesRegularExpression(
      '/^' . $expectedValuePattern . '$/',
      $actual = $field->getString(),
      sprintf(
        $template,
        $fieldName,
        $label,
        $expectedValue,
        $actual,
      ),
    );
  }

}
