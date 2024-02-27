<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\ResponseTextException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Mink\Selector\SelectorsHandler;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Site\Settings;
use Drupal\Driver\Exception\UnsupportedDriverActionException as UnsupportedDrupalDriverActionException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\TagTrait;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\joinup\HtmlManipulator;
use Drupal\joinup\Traits\AntispamTrait;
use Drupal\joinup\Traits\ContextualLinksTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\PageCacheTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TraversingTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup_core\JoinupVersionInterface;
use Drupal\media\Entity\Media;
use Drupal\paragraphs\ParagraphInterface;
use Joinup\TaskRunner\Traits\TaskRunnerTrait;
use Joinup\Traits\DigitQaPipelineAwareTrait;
use LoversOfBehat\TableExtension\Hook\Scope\AfterTableFetchScope;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;
use Symfony\Component\Process\Process;
use WebDriver\Exception;
use WebDriver\Key;

/**
 * Defines generic step definitions.
 */
class FeatureContext extends RawDrupalContext {

  use DigitQaPipelineAwareTrait;
  use AntispamTrait;
  use ContextualLinksTrait;
  use EntityTrait;
  use JavascriptTrait;
  use PageCacheTrait;
  use SearchTrait;
  use TagTrait;
  use TaskRunnerTrait;
  use TraversingTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * The Joinup version, retrieved from the `VERSION` file in the project root.
   *
   * Will contain the contents of the file, or FALSE if the file is not present.
   *
   * @var string|bool
   */
  protected $version;

  /**
   * The latest file ID.
   *
   * @var int
   */
  protected static $lastFileId;

  /**
   * Keeps track of created testing entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[][]
   */
  protected $entities = [];

  /**
   * The mail system storage settings.
   *
   * @var \Drupal\Core\Config\StorableConfigBase
   */
  protected static $mailConfig;

  /**
   * Holds the default settings for the mail server so a revert is possible.
   *
   * @var array
   */
  protected static $savedMailDefaults;

  /**
   * Checks that a 200 OK response occurred.
   *
   * @Then I should get a valid web page
   */
  public function assertSuccessfulResponse(): void {
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Checks that a 403 Access Denied error occurred.
   *
   * @Then I should get an access denied error
   */
  public function assertAccessDenied(): void {
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Assert that certain fields are present on the page or region.
   *
   * @param string $fields
   *   The labels if the fields that are expected to be present.
   * @param string $region
   *   Optional region to search in.
   *
   * @throws \Exception
   *   Thrown when an expected field is not present.
   *
   * @Then (the following )field(s) should be present :fields
   * @Then I should see the :fields field(s) in the :region region
   */
  public function assertFieldsPresent(string $fields, string $region = ''): void {
    $fields = $this->explodeCommaSeparatedStepArgument($fields);
    $region_element = $region ? $this->getRegion($region) : $this->getSession()->getPage();
    $not_found = [];
    foreach ($fields as $field) {
      $is_found = (bool) $this->findAnyFormField($field, $region_element);
      if (!$is_found) {
        $not_found[] = $field;
      }
    }
    if ($not_found) {
      throw new \Exception("Field(s) expected, but not found: " . implode(', ', $not_found));
    }
  }

  /**
   * Assert that certain fields are not present on the page or region.
   *
   * @param string $fields
   *   The labels if the fields that are expected to be absent.
   * @param string $region
   *   Optional region to search in.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Then (the following )field(s) should not be present :fields
   * @Then I should not see the :fields field(s) in the :region region
   */
  public function assertFieldsNotPresent(string $fields, string $region = ''): void {
    $fields = $this->explodeCommaSeparatedStepArgument($fields);
    $region_element = $region ? $this->getRegion($region) : $this->getSession()->getPage();
    foreach ($fields as $field) {
      $is_found = $region_element->findField($field);
      if ($is_found) {
        throw new \Exception("Field should not be found, but is present: " . $field);
      }
    }
  }

  /**
   * Assert that certain fields are present and visible on the page.
   *
   * @param string $fields
   *   Fields.
   *
   * @throws \Exception
   *   Thrown when an expected field is not present or is not visible.
   *
   * @Then (the following )field(s) should be visible :fields
   */
  public function assertFieldsVisible(string $fields): void {
    $fields = $this->explodeCommaSeparatedStepArgument($fields);
    $page = $this->getSession()->getPage();
    $not_found = [];
    $not_visible = [];
    foreach ($fields as $field) {
      $element = $this->findAnyFormField($field, $page);
      if (!$element) {
        $not_found[] = $field;
        continue;
      }
      elseif (!$element->isVisible()) {
        // Retrieve the first standard form item wrapper around our field.
        // Some fields, like text areas or checkboxes, are actually hidden but
        // their label and container are not.
        $wrapper = $element->find('xpath', "ancestor-or-self::div[@class and contains(concat(' ', normalize-space(@class), ' '), ' form-item ')][1]");

        if ($wrapper && !$wrapper->isVisible()) {
          $not_visible[] = $field;
        }
      }
    }

    if ($not_found) {
      throw new \Exception("Field(s) expected, but not found: " . implode(', ', $not_found));
    }
    if ($not_visible) {
      throw new \Exception("Field(s) expected, but not visible: " . implode(', ', $not_visible));
    }
  }

  /**
   * Assert that certain fields are present but not visible on the page.
   *
   * @param string $fields
   *   Fields.
   *
   * @throws \Exception
   *   Thrown when a field is not present or is visible.
   *
   * @Then (the following )field(s) should not be visible :fields
   */
  public function assertFieldsNotVisible(string $fields): void {
    $fields = $this->explodeCommaSeparatedStepArgument($fields);
    $page = $this->getSession()->getPage();
    $not_found = [];
    $visible = [];
    foreach ($fields as $field) {
      $element = $this->findAnyFormField($field, $page);
      if (!$element) {
        $not_found[] = $field;
        continue;
      }

      // Retrieve the first standard form item wrapper around our field.
      // Some fields, like text areas or checkboxes, are actually hidden but
      // their label and container are not.
      $wrapper = $element->find('xpath', "ancestor-or-self::div[@class and contains(concat(' ', normalize-space(@class), ' '), ' form-item ')][1]");
      // Neither the field or its wrapper should be visible at all.
      if ($element->isVisible() || !empty($wrapper) && $wrapper->isVisible()) {
        $visible[] = $field;
      }
    }

    if ($not_found) {
      throw new \Exception("Field(s) expected, but not found: " . implode(', ', $not_found));
    }
    if ($visible) {
      throw new \Exception("Field(s) should not be visible: " . implode(', ', $visible));
    }
  }

  /**
   * Assert that certain fieldsets are present on the page.
   *
   * @param string $fieldsets
   *   The fieldset names to search for, separated by comma.
   *
   * @throws \Exception
   *   Thrown when a fieldset is not found.
   *
   * @Then (the following )field widgets should be present :fieldsets
   * @Then (the following )fieldsets should be present :fieldsets
   */
  public function assertFieldsetsPresent(string $fieldsets): void {
    $fieldsets = $this->explodeCommaSeparatedStepArgument($fieldsets);
    $page = $this->getSession()->getPage();
    $not_found = [];
    foreach ($fieldsets as $fieldset) {
      // Find by fieldset legend or by details summary depending on how the
      // fieldset is rendered.
      $is_found = $page->find('named', ['fieldset', $fieldset])
        || $page->find('xpath', "//details/summary[contains(text(), '$fieldset')]");
      if (!$is_found) {
        // Try to find the fieldset according to the collapse button.
        $not_found[] = $fieldset;
      }
    }
    if ($not_found) {
      throw new \Exception("Fieldset(s) expected, but not found: " . implode(', ', $not_found));
    }
  }

  /**
   * Assert that certain fieldsets are present and visible on the page.
   *
   * @param string $fieldsets
   *   The fieldset names to search for, separated by comma.
   *
   * @throws \Exception
   *   Thrown when a fieldset is not found or is not visible.
   *
   * @Then (the following )field widgets should be visible :fieldsets
   * @Then (the following )fieldsets should be visible :fieldsets
   */
  public function assertFieldsetsVisible(string $fieldsets): void {
    $fieldsets = $this->explodeCommaSeparatedStepArgument($fieldsets);
    $page = $this->getSession()->getPage();
    $not_found = [];
    $not_visible = [];
    foreach ($fieldsets as $fieldset) {
      $is_found = $page->find('named', ['fieldset', $fieldset]);
      if (!$is_found) {
        $not_found[] = $fieldset;
      }

      if (!$is_found->isVisible()) {
        $not_visible[] = $fieldset;
      }
    }

    if ($not_found) {
      throw new \Exception("Fieldset(s) expected, but not found: " . implode(', ', $not_found));
    }
    if ($not_visible) {
      throw new \Exception("Fieldset(s) expected, but not visible: " . implode(', ', $not_visible));
    }
  }

  /**
   * Assert that certain fieldsets are present and visible on the page.
   *
   * @param string $fieldsets
   *   The fieldset names to search for, separated by comma.
   *
   * @throws \Exception
   *   Thrown when a fieldset is not found or is visible.
   *
   * @Then (the following )field widgets should not be visible :fieldsets
   * @Then (the following )fieldsets should not be visible :fieldsets
   */
  public function assertFieldsetsNotVisible(string $fieldsets): void {
    $fieldsets = $this->explodeCommaSeparatedStepArgument($fieldsets);
    $page = $this->getSession()->getPage();
    $not_found = [];
    $visible = [];
    foreach ($fieldsets as $fieldset) {
      $is_found = $page->find('named', ['fieldset', $fieldset]);
      if (!$is_found) {
        $not_found[] = $fieldset;
      }

      if ($is_found->isVisible()) {
        $visible[] = $fieldset;
      }
    }

    if ($not_found) {
      throw new \Exception("Fieldset(s) expected, but not found: " . implode(', ', $not_found));
    }
    if ($visible) {
      throw new \Exception("Fieldset(s) should not be visible: " . implode(', ', $visible));
    }
  }

  /**
   * Checks that the given images are present on the page in the given order.
   *
   * @param \Behat\Gherkin\Node\TableNode $imagesTable
   *   A table with the image file names to look for.
   *
   * @throws \Exception
   *   If one of the images is missing, or if the order of the images is
   *   different than expected.
   *
   * @Then I (should ) have the following images on the page( in this order)
   */
  public function assertImagesPresentInOrder(TableNode $imagesTable): void {
    $this->assertImagesInOrder($imagesTable, FALSE);
  }

  /**
   * Checks that the given images are visible on the page in the given order.
   *
   * @param \Behat\Gherkin\Node\TableNode $imagesTable
   *   A table with the image file names to look for.
   *
   * @throws \Exception
   *   If one of the images is missing or is not visible, or if the order of
   *   the images is different than expected.
   *
   * @Then I (should ) see the following images on the page( in this order)
   */
  public function assertImagesVisibleInOrder(TableNode $imagesTable): void {
    $this->assertImagesInOrder($imagesTable, TRUE);
  }

  /**
   * Checks whether the given images are present or visible on the page.
   *
   * @param \Behat\Gherkin\Node\TableNode $imagesTable
   *   A table with the image file names to look for.
   * @param bool $checkVisibility
   *   Whether visibility should also be checked or not.
   *
   * @throws \Exception
   *   If one of the images is missing (or is not visible), or if the order of
   *   the images is different than expected.
   */
  protected function assertImagesInOrder(TableNode $imagesTable, bool $checkVisibility): void {
    $availableImages = $this->getImages();
    $imageFilenameList = $imagesTable->getColumn(0);
    $firstFile = reset($imageFilenameList);
    // Try to find the first image in the list of available images. Note: the
    // same image might appear multiple times on the page.
    $startIndexes = array_keys(
      array_filter(
        $availableImages,
        function (NodeElement $img) use ($firstFile): bool {
          return $this->imageSourceMatches($firstFile, $img);
        }
      )
    );
    if (empty($startIndexes)) {
      throw new \Exception(sprintf(
        "Cannot find the first image with source '%s' from the given set on the page.",
        $firstFile,
      ));
    }
    $listsToCheck = array_reduce(
      $startIndexes,
      function (array $carry, int $index) use ($availableImages): array {
        $carry[] = array_slice($availableImages, $index);
        return $carry;
      },
      [],
    );

    foreach ($listsToCheck as $imageList) {
      // Check the list.
      foreach ($imageFilenameList as $index => $filename) {
        if (
          !$imageList[$index] instanceof NodeElement ||
          !$this->imageSourceMatches($filename, $imageList[$index])
        ) {
          // First non-matching case. Break this loop and continue with the next
          // image list (if any).
          continue 2;
        }
        if ($checkVisibility && !$imageList[$index]->isVisible()) {
          // Image is not visible.
          continue 2;
        }
      }

      // If we made it here, then the last image list contained all the images
      // in the expected order.
      return;
    }

    $format = $checkVisibility
      ? "The images '%s' are not visible on the page in this order. The first non-visible was '%s'."
      : "The images '%s' cannot be found on the page in this order. The first missing image was '%s'.";
    throw new \Exception(sprintf(
      $format,
      implode("', '", $imageFilenameList),
      $imageFilenameList[$index]
    ));
  }

  /**
   * Checks that a given image is present.
   *
   * @param string $filename
   *   The file name.
   * @param \Behat\Mink\Element\NodeElement|null $element
   *   The element to look for the image in. Optional. Search is performed on
   *   the entire page if omitted or called with NULL.
   *
   * @Then I (should )see the image :filename
   */
  public function assertImagePresent(string $filename, ?NodeElement $element = NULL): void {
    foreach ($this->getAllImages($filename, $element) as $imageNode) {
      try {
        if ($imageNode->isVisible()) {
          return;
        }
      }
      catch (UnsupportedDriverActionException | UnsupportedDrupalDriverActionException) {
      }
    }
    throw new \Exception();
  }

  /**
   * Checks that a given image is not present.
   *
   * @param string $filename
   *   The filename.
   * @param \Behat\Mink\Element\NodeElement|null $element
   *   The element to look for the image in. Optional. Search is performed on
   *   the entire page if omitted or called with NULL.
   *
   * @Then I should not see the image :filename
   */
  public function assertImageNotPresent(string $filename, ?NodeElement $element = NULL): void {
    if ($imageNode = $this->getImage($filename, $element)) {
      try {
        Assert::assertFalse($imageNode->isVisible());
        return;
      }
      catch (UnsupportedDriverActionException | UnsupportedDrupalDriverActionException) {
      }
    }
    Assert::assertFalse($this->hasImage($filename, $element));
  }

  /**
   * Checks that a given image is present in a given tile.
   *
   * @param string $filename
   *   The filename.
   * @param string $tile
   *   The tile title.
   *
   * @Then I (should )see the image ":filename" in the :tile tile
   */
  public function assertImagePresentInRegion(string $filename, string $tile): void {
    $tileElement = $this->getTileByHeading($tile);
    Assert::assertNotEmpty($tileElement, "Tile cannot be found");
    $this->assertImagePresent($filename, $tileElement);
  }

  /**
   * Checks that the tile has any (or the specified number of) image(s).
   *
   * @param string $count
   *   The expected number of images in the tile, the string "no" to ensure that
   *   there is no image (same check as "0"), or the string "an" or "some" to
   *   verify that there is at least one image in the tile.
   * @param string $tile
   *   The title of the tile.
   *
   * @Then there should be :count image(s) in the :tile tile
   */
  public function assertAnyImagePresentInTile(string $count, string $tile): void {
    $tileElement = $this->getTileByHeading($tile);
    Assert::assertNotEmpty($tileElement, "Tile cannot be found");
    $imageNodes = $this->getImages($tileElement);
    if (in_array($count, ['an', 'some'], TRUE)) {
      Assert::assertNotEmpty($imageNodes);
      return;
    }

    // Perform count check. First, make sure the count parameter is a number, or
    // the 'no' string.
    $countInt = intval($count);
    if ((string) $countInt !== $count && $count !== 'no') {
      throw new \Exception("Count must be either the string 'an', or a number");
    }
    Assert::assertCount($countInt, $imageNodes);
  }

  /**
   * Checks that a given image is not present in a given tile.
   *
   * @param string $filename
   *   The filename.
   * @param string $tile
   *   The tile title.
   *
   * @Then I should not see the image :filename in the :tile tile
   */
  public function assertImageNotPresentInRegion(string $filename, string $tile): void {
    $tileElement = $this->getTileByHeading($tile);
    Assert::assertNotEmpty($tileElement, "Tile cannot be found");
    $this->assertImageNotPresent($filename, $tileElement);
  }

  /**
   * Maximize the browser window for javascript tests so elements are visible.
   *
   * @Given I maximize the browser window
   */
  public function maximizeBrowserWindow(): void {
    $this->getSession()->getDriver()->maximizeWindow();
  }

  /**
   * Checks that the option with the given text is selected.
   *
   * @param string $text
   *   Text value of the option to check.
   *
   * @throws \Exception
   *   Thrown when an option with the given text does not exist, or when it is
   *   not selected.
   *
   * @Then the option :option should be selected
   */
  public function assertOptionSelected(string $text): void {
    $option = $this->getSession()->getPage()->find('xpath', '//option[text()="' . $text . '"]');

    if (!$option) {
      throw new \Exception("Option with text $text not found in the page.");
    }

    if (!$option->isSelected()) {
      throw new \Exception("The option with text $text is not selected.");
    }
  }

  /**
   * Checks that the option with the given text is not selected.
   *
   * @param string $text
   *   Text value of the option to check.
   *
   * @throws \Exception
   *   Thrown when an option with the given text does not exist, or when it is
   *   selected.
   *
   * @Then the option :option should not be selected
   */
  public function assertOptionNotSelected(string $text): void {
    $option = $this->getSession()->getPage()->find('xpath', '//option[text()="' . $text . '"]');

    if (!$option) {
      throw new \Exception("Option with text $text not found in the page.");
    }

    if ($option->isSelected()) {
      throw new \Exception("The option with text $text is selected.");
    }
  }

  /**
   * Find the selected option of the select and check the text.
   *
   * @param string $option
   *   Text value of the option to find.
   * @param string $select
   *   CSS selector of the select field.
   *
   * @throws \Exception
   *   Thrown when the select is not found in the page or the selected option is
   *   not the expected one.
   *
   * @Then the option with text :option from select :select is selected
   * @Then the option with text :option from select :select should be selected
   */
  public function assertFieldOptionSelected(string $option, string $select): void {
    $this->assertFieldOptionSelectedInRegion($option, $select);
  }

  /**
   * Checks if an option is selected in a specific select element in a region.
   *
   * @param string $option
   *   Text value of the option to find.
   * @param string $select
   *   CSS selector of the select field.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region to search in. Defaults to the whole page.
   *
   * @throws \Exception
   *   Thrown when the select is not found in the page or the selected option is
   *   not the expected one.
   */
  protected function assertFieldOptionSelectedInRegion(string $option, string $select, ?TraversableElement $region = NULL): void {
    if (empty($region)) {
      $region = $this->getSession()->getPage();
    }

    $element = $this->findSelect($select, $region);
    if (!$element) {
      throw new \Exception(sprintf('The select "%s" was not found.', $select));
    }

    $option_element = $element->find('xpath', '//option[@selected="selected"]');
    if (!$option_element) {
      throw new \Exception(sprintf('No option is selected in the %s select', $select));
    }

    if ($option_element->getText() !== $option) {
      throw new \Exception(sprintf('The option "%s" was expected to be selected, but %s was selected instead.', $option, $this->getSession()->getCurrentUrl(), $option_element->getHtml()));
    }
  }

  /**
   * Checks that a certain radio input is selected in a specific field.
   *
   * @param string $radio
   *   The label of the radio input to find.
   * @param string $field
   *   The label of the field the radio is part of.
   *
   * @throws \Exception
   *   Thrown when the field or the radio is not found, or if the radio is not
   *   selected.
   *
   * @Then the radio button :radio from field :field should be selected
   */
  public function assertFieldRadioSelected(string $radio, string $field): void {
    // Find the grouping fieldset that contains the radios field.
    $fieldset = $this->getSession()->getPage()->find('named', [
      'fieldset',
      $field,
    ]);

    if (!$field) {
      throw new \Exception("The field '$field' was not found in the page.");
    }

    // Find the field inside the container itself. Use the findField() instead
    // of custom xpath because we are trying to find the radio by label.
    $input = $fieldset->findField($radio);

    // Verify that we have found a valid '//input[@type="radio"]'.
    if (!$input || $input->getTagName() !== 'input' || $input->getAttribute('type') !== 'radio') {
      throw new \Exception("The radio '$radio' was not found in the page.");
    }

    if (!$input->isChecked()) {
      throw new \Exception("The radio '$radio' is not selected.");
    }
  }

  /**
   * Asserts that the radio button option should be selected.
   *
   * @param string $radio
   *   The radio button title.
   *
   * @Then the :radio radio button should not be selected
   */
  public function assertRadioButtonNotChecked(string $radio): void {
    $session = $this->getSession();
    $page = $session->getPage();
    $radio = $page->find('named', ['radio', $radio]);
    if ($radio->isChecked()) {
      throw new ExpectationException($session->getDriver(), 'The radio button is checked but it should not be.');
    }
  }

  /**
   * Checks that a select element does not have the given text option selected.
   *
   * @param string $option
   *   Text value of the option to find.
   * @param string $select
   *   CSS selector of the select field.
   *
   * @throws \Exception
   *
   * @Then the option with text :option from select :select is not selected
   */
  public function assertFieldOptionNotSelected(string $option, string $select): void {
    $selectField = $this->getSession()->getPage()->find('css', $select);
    if ($selectField === NULL) {
      throw new \Exception(sprintf('The select "%s" was not found in the page %s', $select, $this->getSession()->getCurrentUrl()));
    }

    $optionField = $selectField->find('xpath', '//option[@selected="selected"]');
    if ($optionField !== NULL) {
      if ($optionField->getHtml() == $option) {
        throw new \Exception(sprintf('The option "%s" was selected in the page %s', $option, $this->getSession()->getCurrentUrl()));
      }
    }
  }

  /**
   * Checks if a node of a certain type with a given title exists.
   *
   * @param string $type
   *   The node type.
   * @param string $title
   *   The title of the node.
   *
   * @Then I should have a :type (content )page titled :title
   */
  public function assertContentPageByTitle(string $type, string $title): void {
    $type = $this->getEntityByLabel('node_type', $type);
    // If the node doesn't exist, the exception will be thrown here.
    $this->getEntityByLabel('node', $title, $type->id());
  }

  /**
   * Deletes the user account with the given name.
   *
   * This intended to be used for user accounts that are created through the UI
   * and are not cleaned up automatically when a test ends.
   *
   * @param string $username
   *   The name of the user to delete.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when an error occurs while the user is being deleted.
   *
   * @Then I delete the :username user
   */
  public function deleteUser(string $username): void {
    /** @var \Drupal\user\UserInterface $user */
    $user = $this->getUserByName($username);
    $user->delete();
  }

  /**
   * Checks whether an element with a CSS selector can be found on the page.
   *
   * @param string $selector
   *   The CSS selector of the element.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The first node element on the page matching the selector.
   *
   * @throws \Exception
   *   If the element cannot be found.
   *
   * @Then the page should have an element with CSS selector :selector
   */
  public function getElementByCss(string $selector): NodeElement {
    if ($element = $this->getSession()->getPage()->find('css', $selector)) {
      return $element;
    }

    throw new \Exception(sprintf(
      "Element with CSS selector '%s' could not be found on the page",
      $selector
    ));
  }

  /**
   * Click on an element by css class.
   *
   * @param string $element
   *   The element label to click.
   *
   * @Then /^I click on element "([^"]*)"$/
   */
  public function iClickOn(string $element): void {
    $this->getElementByCss($element)->click();
  }

  /**
   * Clicks on label by text.
   *
   * @param string $element
   *   The label to click.
   *
   * @Then I click on label :element
   *
   * @throws \Exception
   */
  public function iClickOnLabel(string $element): void {
    $page = $this->getSession()->getPage();
    $labelElement = $page->find('xpath', "//label[text()='$element']");
    if (!$labelElement) {
      throw new \Exception($element . " could not be found");
    }

    $labelElement->click();
  }

  /**
   * Clicks a contextual link directly, without the need for javascript.
   *
   * @param string $text
   *   The text of the link.
   * @param string $region
   *   The name of the region.
   *
   * @throws \Exception
   *   When either the region or the link are not found.
   *
   * @Then I click the contextual link :text in the :region region
   */
  public function iClickTheContextualLinkInTheRegion(string $text, string $region): void {
    $this->clickContextualLink($this->getRegion($region), $text);
  }

  /**
   * Asserts that a certain contextual link is present in a region.
   *
   * @param string $text
   *   The text of the link.
   * @param string $region
   *   The name of the region.
   *
   * @throws \Exception
   *   Thrown when the contextual link is not found in the region.
   *
   * @Then I (should )see the contextual link :text in the :region region
   */
  public function assertContextualLinkInRegionPresent(string $text, string $region): void {
    $links = $this->findContextualLinkPaths($this->getRegion($region));

    if (!isset($links[$text])) {
      throw new \Exception(sprintf('Contextual link %s expected but not found in the region %s', $text, $region));
    }
  }

  /**
   * Asserts that a certain contextual link is present in the page.
   *
   * @param string $text
   *   The text of the link.
   *
   * @throws \Exception
   *   Thrown when the contextual link is not found in the page.
   *
   * @Then I (should )see the contextual link :text
   */
  public function assertContextualLinkInPagePresent(string $text): void {
    $region = $this->getSession()->getPage();
    $links = $this->findContextualLinkPaths($region);

    if (!isset($links[$text])) {
      throw new \Exception(sprintf('Contextual link %s expected but not found in page.', $text));
    }
  }

  /**
   * Asserts that a certain contextual link is not present in a region.
   *
   * @param string $text
   *   The text of the link.
   * @param string $region
   *   The name of the region.
   *
   * @throws \Exception
   *   Thrown when the contextual link is found in the region.
   *
   * @Then I should not see the contextual link :text in the :region region
   */
  public function assertContextualLinkInRegionNotPresent(string $text, string $region): void {
    $links = $this->findContextualLinkPaths($this->getRegion($region));

    if (isset($links[$text])) {
      throw new \Exception(sprintf('Unexpected contextual link %s found in the region %s', $text, $region));
    }
  }

  /**
   * Asserts that a certain contextual link is not present in the page.
   *
   * @param string $text
   *   The text of the link.
   *
   * @throws \Exception
   *   Thrown when the contextual link is found in the page.
   *
   * @Then I should not see the contextual link :text
   */
  public function assertContextualLinkInPageNotPresent(string $text): void {
    $region = $this->getSession()->getPage();
    $links = $this->findContextualLinkPaths($region);

    if (isset($links[$text])) {
      throw new \Exception(sprintf('Unexpected contextual link %s found in the page', $text));
    }
  }

  /**
   * Asserts that no contextual links are present in a region.
   *
   * @param string $region
   *   The name of the region.
   *
   * @throws \Exception
   *   Thrown when any contextual link is found in the region.
   *
   * @Then I should not see any contextual links in the :region region
   */
  public function assertNoContextualLinksInRegion(string $region): void {
    $links = $this->findContextualLinkPaths($this->getRegion($region));

    if (!empty($links)) {
      throw new \Exception(sprintf('Unexpected contextual links found in the region %s', $region));
    }
  }

  /**
   * Checks that the contextual links button is visible in the browser.
   *
   * This checks actual visibility in the browser, so this needs the
   * '@javascript' tag to be present on the test scenario.
   *
   * @param string $region
   *   The region in which the contextual link is expected to be visible.
   *
   * @Then I (should )see the contextual links button in the :region( region)
   * @Then the contextual links button should be visible in the :region( region)
   */
  public function assertContextualLinkButtonVisible(string $region): void {
    $button = $this->findContextualLinkButton($this->getRegion($region));
    $this->assertVisuallyVisible($button);
  }

  /**
   * Clicks the contextual links button in the given region.
   *
   * @param string $region
   *   The name of the region where the contextual links button resides.
   *
   * @When I click the contextual links button in the :region( region)
   */
  public function clickContextualLinkButton(string $region): void {
    $button = $this->findContextualLinkButton($this->getRegion($region));
    $button->click();
  }

  /**
   * Checks that the given named element is not visible for human eyes.
   *
   * This is similar to methods like MinkContext::assertLinkRegion() but
   * instead of verifying the presence of the element in the DOM it checks
   * with the browser if the element is actually invisible.
   *
   * This is intended for verifying things like hover states.
   *
   * @param string $locator
   *   The xpath locator.
   * @param string $element
   *   The element tag.
   * @param string $region
   *   The region selector.
   *
   * @Then the :locator :element in the :region( region) should not be visible
   */
  public function assertElementNotVisibleInRegion(string $locator, string $element, string $region): void {
    $region = $this->getRegion($region);
    $element = $this->findNamedElementInRegion($locator, $element, $region);
    $this->scrollElementIntoView($element);
    $this->assertNotVisuallyVisible($element);
  }

  /**
   * Checks that the given named element is visible for human eyes.
   *
   * This is similar to methods like MinkContext::assertLinkRegion() but instead
   * of verifying the presence of the element in the DOM it checks with the
   * browser if the element is actually visible.
   *
   * This is intended for verifying things like hover states.
   *
   * @param string $locator
   *   The xpath locator.
   * @param string $element
   *   The element tag.
   * @param string $region
   *   The region selector.
   *
   * @Then the :locator :element in the :region( region) should be visible
   */
  public function assertElementVisibleInRegion(string $locator, string $element, string $region): void {
    $region = $this->getRegion($region);
    $element = $this->findNamedElementInRegion($locator, $element, $region);
    if (!$element->getParent()->getParent()->hasClass('contextual-links')) {
      $this->scrollElementIntoView($element);
    }
    $this->assertVisuallyVisible($element);
  }

  /**
   * Finds a vertical tab given its title and clicks it.
   *
   * @param string $tab
   *   The tab title.
   *
   * @throws \Exception
   *   When the tab is not found on the page.
   *
   * @When I click( the) :tab tab
   */
  public function clickVerticalTabLink(string $tab): void {
    // When this is running in a browser without JavaScript the vertical tabs
    // are rendered as detail elements.
    if (!$this->hasJavascriptSupport()) {
      return;
    }

    $this->scrollToTop();
    $this->findVerticalTab($tab)->clickLink($tab);
  }

  /**
   * Fills a multi-value field.
   *
   * In Drupal a field can have a cardinality bigger than one. In that case,
   * the field widget will be rendered multiple times. This method will fill
   * each item with the corresponding value.
   * This doesn't handle widgets with multiple inputs and it relies on the
   * number of items to match the number of values. Clicking the
   * button to add more items is left to the user.
   *
   * @param string $field
   *   The name of the field.
   * @param string $values
   *   A comma separated list of values.
   *
   * @throws \Exception
   *   When the field cannot be found or the number of values is different from
   *   the number of elements found.
   *
   * @When I fill in :field with values :values
   */
  public function fillFieldWithValues(string $field, string $values): void {
    $values = $this->explodeCommaSeparatedStepArgument($values);

    /** @var \Behat\Mink\Element\NodeElement[] $items */
    $items = $this->getSession()->getPage()->findAll('named', ['field', $field]);

    if (empty($items)) {
      throw new \Exception("Cannot find field $field.");
    }

    if (count($items) !== count($values)) {
      throw new \Exception('Expected ' . count($values) . ' items for field ' . $field . ', found ' . count($items));
    }

    foreach ($items as $delta => $item) {
      $item->setValue($values[$delta]);
    }
  }

  /**
   * Asserts that a whole region is not present in the page.
   *
   * @param string $region
   *   The name of the region.
   *
   * @throws \Exception
   *   Thrown when the region is found in the page.
   *
   * @Then I should not see the :region region
   */
  public function assertRegionNotPresent(string $region): void {
    $session = $this->getSession();
    $element = $session->getPage()->find('region', $region);
    if ($element) {
      throw new \Exception(sprintf('Region "%s" found on the page %s.', $region, $session->getCurrentUrl()));
    }
  }

  /**
   * Asserts that the page title tag equals to some text.
   *
   * The assertion strips off the possible suffix "| Joinup".
   *
   * @param string $text
   *   The text to search for.
   *
   * @throws \Exception
   *   Thrown when the title tag is not found or the text doesn't match.
   *
   * @Then the HTML title of the page should be :text
   */
  public function assertPageTitleTagContainsText(string $text): void {
    $session = $this->getSession();
    $page_title = $session->getPage()->find('xpath', '//head/title');
    if (!$page_title) {
      throw new \Exception(sprintf('Page title tag not found on the page "%s".', $session->getCurrentUrl()));
    }

    $page_title = $page_title->getText() ?: $page_title->getHtml();
    if (!$page_title) {
      throw new \Exception(sprintf('Page title tag is found but contains no text on page "%s".', $session->getCurrentUrl()));
    }

    if (strpos($page_title, ' | ') !== FALSE) {
      $page_title = implode(' | ', explode(' | ', $page_title, -1));
    }

    $page_title = trim($page_title);
    if ($page_title !== $text) {
      throw new \Exception(sprintf('Expected page title is "%s", but "%s" found.', $text, $page_title));
    }
  }

  /**
   * Asserts that the page contains a certain capitalised heading.
   *
   * @param string $heading
   *   The heading to search for.
   *
   * @Then I (should )see the capitalised heading :heading
   */
  public function assertCapitalisedHeading(string $heading): void {
    $heading = strtoupper($heading);
    $element = $this->getSession()->getPage();
    foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
      $results = $element->findAll('css', $tag);
      foreach ($results as $result) {
        if ($result->getText() == $heading) {
          return;
        }
      }
    }
    throw new \Exception(sprintf("The text '%s' was not found in any heading on the page %s", $heading, $this->getSession()->getCurrentUrl()));
  }

  /**
   * Checks multiple headings on the page.
   *
   * Provide data in the following format:
   * | Heading 1 |
   * | Heading 2 |
   * | ...       |
   *
   * @param \Behat\Gherkin\Node\TableNode $headingsTable
   *   A list of headings.
   *
   * @Then I (should )see the following headings:
   */
  public function assertHeadings(TableNode $headingsTable): void {
    $page = $this->getSession()->getPage();
    $headings = $headingsTable->getColumn(0);
    foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
      $results = $page->findAll('css', $tag);
      foreach ($results as $result) {
        $key = array_search($result->getText(), $headings);
        if ($key === FALSE) {
          continue;
        }
        unset($headings[$key]);
      }
    }
    if (!empty($headings)) {
      throw new \Exception(sprintf("The following headings were not found on the page %s: '%s'", $this->getSession()->getCurrentUrl(), implode(', ', $headings)));
    }
  }

  /**
   * Checks if multiple headings are not present on the page.
   *
   * Provide data in the following format:
   * | Heading 1 |
   * | Heading 2 |
   * | ...       |
   *
   * @param \Behat\Gherkin\Node\TableNode $headingsTable
   *   A list of headings.
   *
   * @Then I should not see the following headings:
   */
  public function assertNoHeadings(TableNode $headingsTable): void {
    $page = $this->getSession()->getPage();
    $headings = $headingsTable->getColumn(0);
    $found_headings = [];
    foreach (['h1', 'h2', 'h3', 'h4', 'h5', 'h6'] as $tag) {
      $results = $page->findAll('css', $tag);
      foreach ($results as $result) {
        $key = array_search($result->getText(), $headings);
        if ($key !== FALSE) {
          $found_headings[] = $headings[$key];
        }
      }
    }
    if (!empty($found_headings)) {
      throw new \Exception(sprintf("The following headings were found on the page %s, but they shouldn't have been: '%s'", $this->getSession()->getCurrentUrl(), implode(', ', $found_headings)));
    }
  }

  /**
   * Checks multiple lines of text on the page.
   *
   * Provide data in the following format:
   * | Text 1 |
   * | Text 2 |
   * | ...    |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A list of texts.
   *
   * @Then I (should )see the following lines of text:
   */
  public function assertTexts(TableNode $table): void {
    $lines = $table->getColumn(0);
    $errors = [];
    foreach ($lines as $line) {
      try {
        $this->assertSession()->pageTextContains($line);
      }
      catch (ResponseTextException $e) {
        $errors[] = $line;
      }
    }
    if (!empty($errors)) {
      throw new \Exception(sprintf("The following lines of text were not found on the page %s: '%s'", $this->getSession()->getCurrentUrl(), implode(', ', $errors)));
    }
  }

  /**
   * Checks that multiple lines of text are not present on the page.
   *
   * Provide data in the following format:
   * | Text 1 |
   * | Text 2 |
   * | ...    |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A list of texts.
   *
   * @Then I should not see the following lines of text:
   */
  public function assertNoTexts(TableNode $table): void {
    $lines = $table->getColumn(0);
    $errors = [];
    foreach ($lines as $line) {
      try {
        $this->assertSession()->pageTextNotContains($line);
      }
      catch (ResponseTextException $e) {
        $errors[] = $line;
      }
    }
    if (!empty($errors)) {
      throw new \Exception(sprintf("The following lines of text were found on the page %s: '%s'", $this->getSession()->getCurrentUrl(), implode(', ', $errors)));
    }
  }

  /**
   * Checks multiple links on the page.
   *
   * Provide data in the following format:
   * | Link text 1 |
   * | Link text 2 |
   * | ...         |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A list of links.
   * @param string $region
   *   Optional region to check.
   *
   * @Then I (should )see the following links:
   * @Then I (should )see the following links in the :region( region):
   */
  public function assertLinks(TableNode $table, string $region = ''): void {
    $region_element = $region ? $this->getRegion($region) : $this->getSession()->getPage();
    $links = $table->getColumn(0);
    $errors = [];
    foreach ($links as $link) {
      $element = $region_element->findLink($link);
      if (empty($element)) {
        $errors[] = $link;
      }
    }
    if (!empty($errors)) {
      throw new \Exception(sprintf("The following links were not found on the page %s: '%s'", $this->getSession()->getCurrentUrl(), implode(', ', $errors)));
    }
  }

  /**
   * Checks if multiple links are not present on the page.
   *
   * Provide data in the following format:
   * | Link text 1 |
   * | Link text 2 |
   * | ...         |
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   A list of links.
   * @param string $region
   *   Optional region to check.
   *
   * @Then I should not see the following links:
   * @Then I should not see the following links in the :region( region):
   */
  public function assertNoLinks(TableNode $table, string $region = ''): void {
    $region_element = $region ? $this->getRegion($region) : $this->getSession()->getPage();
    $links = $table->getColumn(0);
    $errors = [];
    foreach ($links as $link) {
      $element = $region_element->findLink($link);
      if (!empty($element)) {
        $errors[] = $link;
      }
    }
    if (!empty($errors)) {
      throw new \Exception(sprintf("The following links were found on the page %s: '%s'", $this->getSession()->getCurrentUrl(), implode(', ', $errors)));
    }
  }

  /**
   * Checks that the page is cacheable.
   *
   * @Then the page should be cacheable
   */
  public function assertPageCacheable(): void {
    Assert::assertTrue($this->isPageCacheable());
  }

  /**
   * Checks that the page is not cacheable.
   *
   * @Then the page should not be cacheable
   */
  public function assertPageNotCacheable(): void {
    Assert::assertFalse($this->isPageCacheable());
  }

  /**
   * Checks that the page is cached.
   *
   * @Then the page should be cached
   */
  public function assertPageCached(): void {
    Assert::assertTrue($this->isPageCached());
  }

  /**
   * Checks that the page is not cached.
   *
   * @Then the page should not be cached
   */
  public function assertPageNotCached(): void {
    Assert::assertFalse($this->isPageCached());
  }

  /**
   * Checks if a checkbox or a radio in a row with a given text is checked.
   *
   * @param string $text
   *   Text in the row.
   *
   * @throws \Exception
   *   If the page contains no rows, no row contains the text or the row
   *   contains no checkbox or radio button.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If the checkbox is unchecked.
   *
   * @Then the row :text is selected/checked
   */
  public function assertRowIsChecked(string $text): void {
    if (!$this->getCheckboxOrRadioByRowText($text)->isChecked()) {
      throw new ExpectationException("Checkbox/radio-button in '$text' row is unchecked/unselected but it should be checked/selected.", $this->getSession()->getDriver());
    }
  }

  /**
   * Checks if a checkbox or a radio in a row with a given text is not checked.
   *
   * @param string $text
   *   Text in the row.
   *
   * @throws \Exception
   *   If the page contains no rows, no row contains the text or the row
   *   contains no checkbox or radio button.
   * @throws \Behat\Mink\Exception\ExpectationException
   *   If the checkbox is checked.
   *
   * @Then the row :text is not selected/checked
   */
  public function assertRowIsNotChecked(string $text): void {
    if ($this->getCheckboxOrRadioByRowText($text)->isChecked()) {
      throw new ExpectationException("Checkbox/radio-button in '$text' row is checked/selected but it should be unchecked/unselected.", $this->getSession()->getDriver());
    }
  }

  /**
   * Checks a checkbox or a radio button in a table row containing a given text.
   *
   * @param string $text
   *   Text in the row.
   *
   * @throws \Exception
   *   If the page contains no rows, no row contains the text or the row
   *   contains no checkbox or radio button.
   *
   * @Given I select/check the :text row
   */
  public function checkTableselectRow(string $text): void {
    $element = $this->getCheckboxOrRadioByRowText($text);
    $this->scrollElementIntoView($element);
    if ($element->getAttribute('type') === 'checkbox') {
      $element->check();
    }
    else {
      $element->getParent()->selectFieldOption($element->getAttribute('name'), $element->getAttribute('value'));
    }
  }

  /**
   * Unchecks a checkbox or a radio in a table row containing a given text.
   *
   * @param string $text
   *   Text in the row.
   *
   * @throws \Exception
   *   If the page contains no rows, no row contains the text or the row
   *   contains no checkbox or radio button.
   * @throws \InvalidArgumentException
   *   If this step definition was used on a radio button.
   *
   * @Given I deselect/uncheck the :text row
   */
  public function uncheckTableselectRow(string $text): void {
    $element = $this->getCheckboxOrRadioByRowText($text);
    if ($element->getAttribute('type') === 'radio') {
      throw new \InvalidArgumentException("A radio button cannot be unselected.");
    }
    $element->uncheck();
  }

  /**
   * Asserts that a table row contains a link.
   *
   * @param string $link_text
   *   The link to search for.
   * @param string $row_text
   *   Text contained in a row. Must be unique.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The found link.
   *
   * @Given I should see the :link_text link in the :row_text row
   */
  public function tableRowContainsLink(string $link_text, string $row_text): NodeElement {
    $element = $this->getRowByRowText($row_text);
    $link = $element->findLink($link_text);
    Assert::assertNotEmpty($link);
    return $link;
  }

  /**
   * Clicks a link to a given row.
   *
   * @param string $link_text
   *   The link to search for.
   * @param string $row_text
   *   Text contained in a row. Must be unique.
   *
   * @Given I click the :link_text link in the :row_text row
   */
  public function clickLinkInRow(string $link_text, string $row_text): void {
    $link = $this->tableRowContainsLink($link_text, $row_text);
    $link->click();
  }

  /**
   * Searches the page for a row that includes the given text.
   *
   * @param string $text
   *   The text to search for.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row element.
   */
  protected function getRowByRowText(string $text): NodeElement {
    $page = $this->getSession()->getPage();
    $rows = $page->findAll('css', 'tr');
    if (empty($rows)) {
      throw new \Exception(sprintf('No rows found on the page %s', $this->getSession()->getCurrentUrl()));
    }
    $found = FALSE;
    /** @var \Behat\Mink\Element\NodeElement $row */
    foreach ($rows as $row) {
      if (strpos($row->getText(), $text) !== FALSE) {
        $found = TRUE;
        break;
      }
    }
    if (!$found) {
      throw new \Exception(sprintf('Failed to find a row containing "%s" on the page %s', $text, $this->getSession()->getCurrentUrl()));
    }

    return $row;
  }

  /**
   * Asserts that a checkbox/radio exists in a row containing a given text.
   *
   * @param string $text
   *   Text in the row.
   *
   * @throws \Exception
   *   If the page contains no rows, no row contains the text or the row
   *   contains no checkbox or radio button.
   *
   * @Then the :text table row contains a checkbox/radio
   */
  public function assertCheckboxOrRadioExistsInRow(string $text): void {
    $this->getCheckboxOrRadioByRowText($text);
  }

  /**
   * Asserts that a checkbox/radio doesn't exists in a row with given text.
   *
   * @param string $text
   *   Text in the row.
   *
   * @throws \Exception
   *   If the page contains no rows, no row contains the text or the row
   *   contains no checkbox or radio button.
   *
   * @Then the :text table row doesn't contain a checkbox/radio
   */
  public function assertCheckboxOrRadioNotExistsInRow(string $text): void {
    $row = $this->getRowByRowText($text);
    if ($row->find('css', 'input[type="checkbox"],input[type="radio"]')) {
      throw new ExpectationFailedException("The row '$text' contains a checkbox/radio but it should not.");
    }
  }

  /**
   * Finds a checkbox or a radio button in a table row containing a given text.
   *
   * @param string $text
   *   Text in the row.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The checkbox element.
   *
   * @throws \Exception
   *   If the page contains no rows, no row contains the text or the row
   *   contains no checkbox or radio button.
   */
  protected function getCheckboxOrRadioByRowText(string $text): NodeElement {
    $row = $this->getRowByRowText($text);
    if (!$element = $row->find('css', 'input[type="checkbox"],input[type="radio"]')) {
      throw new \Exception(sprintf('The row "%s" on the page "%s" contains no checkbox or radio button', $text, $this->getSession()->getCurrentUrl()));
    }

    return $element;
  }

  /**
   * Runs a batch operations process.
   *
   * @Given I wait for the batch process to finish
   */
  public function waitForBatchProcess(): void {
    while ($refresh = $this->getSession()
      ->getPage()
      ->find('css', 'meta[http-equiv="Refresh"]')) {
      $content = $refresh->getAttribute('content');
      $url = str_replace('0; URL=', '', $content);
      $this->getSession()->visit($url);
    }
  }

  /**
   * Clears the static cache and resets cache invalidators checksums.
   *
   * Static caches are typically cleared at the end of the request since a
   * typical web request is short lived and the process disappears when the page
   * is delivered. But if a Behat test is using DrupalContext then Drupal will
   * be bootstrapped early on (in the BeforeSuiteScope step). This starts a
   * request which is not short lived, but can live for several minutes while
   * the tests run. During the lifetime of this request there will be steps
   * executed that do requests of their own, changing the state of the Drupal
   * site. This does not however update any of the statically cached data of the
   * parent request, so this is totally unaware of the changes. This causes
   * unexpected behaviour like the failure to invalidate some caches because
   * DatabaseCacheTagsChecksum::invalidateTags() keeps a local storage of which
   * cache tags were invalidated, and this is not reset in time.
   *
   * For this reason, we clear the static cache after each step. Note that this
   * is not a performance issue, it has been benchmarked and the difference is
   * negligible.
   *
   * @param \Behat\Behat\Hook\Scope\AfterStepScope $event
   *   The after step scope event.
   *
   * @see \Drupal\Core\Cache\DatabaseCacheTagsChecksum
   * @see https://github.com/jhedstrom/drupalextension/issues/133
   *
   * @AfterStep
   */
  public function clearCacheTagsStaticCache(AfterStepScope $event): void {
    if ($this->getScenario()->hasTag('api') || $event->getFeature()->hasTag('api')) {
      // Sample tests needing this:
      // - solutions_page.feature
      // - collection/homepage.feature
      // - collection/last_update_time.feature.
      parent::clearStaticCaches();
    }

    // Sample tests needing this:
    // - unsubscribe.feature
    // - homepage.feature
    // - my_subscriptions.feature
    // - legal_notice.feature.
    \Drupal::getContainer()->get('entity.memory_cache')->invalidateAll();

    // Sample tests needing this:
    // - collection.subscribe-on-join.feature.
    \Drupal::getContainer()->get('cache.static')->invalidateAll();
  }

  /**
   * Forces the indexing of new or changed content after each step.
   *
   * When a Search API index is configured with the 'options.index_directly'
   * setting set to TRUE, the entity is not indexed immediately after was saved,
   * in hook_entity_update(), instead the indexing is postponed to the end of
   * the request. This is OK when operating manually the site, but when this is
   * wrapped in the test "page request", the index will occur only after all the
   * steps were executed and, as an effect, entities created across the steps
   * are not indexed yet when the next step is executed. For this reason, we
   * force an indexing after each step.
   *
   * @see https://www.drupal.org/project/search_api/issues/2922525
   *
   * @AfterStep
   */
  public function indexEntities(): void {
    \Drupal::service('search_api.post_request_indexing')->destruct();
  }

  /**
   * Waits until a text is dynamically added to the page.
   *
   * @param string $text
   *   The text to search for.
   *
   * @Given I wait until the page contains the text :text
   */
  public function iWaitUntilPageContains(string $text): void {
    $text = addslashes($text);
    $result = $this->getSession()->wait(60000,
      'document.evaluate(\'//*[contains(., "' . $text . '")]\', document, null, XPathResult.ANY_TYPE, null).iterateNext()'
    );
    Assert::assertTrue($result);
  }

  /**
   * Strips elements from tables that are only readable by screen readers.
   *
   * @param \LoversOfBehat\TableExtension\Hook\Scope\AfterTableFetchScope $scope
   *   The scope class.
   *
   * @AfterTableFetch
   */
  public static function stripScreenReaderElements(AfterTableFetchScope $scope): void {
    $html_manipulator = new HtmlManipulator($scope->getHtml());
    $scope->setHtml($html_manipulator->removeElements('.visually-hidden')->html());
  }

  /**
   * Fills in the autocomplete field with the given text.
   *
   * This differs from MinkContext::fillField() in that this will no remove the
   * focus on the field after entering the text, so that the autocomplete
   * results will not disappear. The final action taken on the field will be the
   * "keyup" event for the last character.
   *
   * @param string $field
   *   The ID, name, label or value of the autocomplete field to fill in.
   * @param string $value
   *   The text to type in the autocomplete field.
   *
   * @When I type :value in the :field autocomplete field
   */
  public function fillAutoCompleteField(string $field, string $value): void {
    $this->assertJavaScriptEnabledBrowser();

    $driver = $this->getSession()->getDriver();
    if (!$driver instanceof Selenium2Driver) {
      throw new \RuntimeException("Only Selenium is currently supported for typing in autocomplete fields.");
    }

    $xpath = (new SelectorsHandler())->selectorToXpath('named', [
      'field',
      $field,
    ]);
    try {
      $element = $driver->getWebDriverSession()->element('xpath', $xpath);
    }
    catch (Exception $e) {
      throw new \RuntimeException("Field with locator '$field' was not found in the page.");
    }

    // Clear any existing data in the field before typing the new data.
    $value = str_repeat(Key::BACKSPACE . Key::DELETE, strlen($element->attribute('value'))) . $value;

    // Fill in the field by directly using the postValue() method of the
    // webdriver. This executes the keystrokes that make up the text but will
    // not remove focus from the field so the autocomplete results remain
    // visible and can be inspected.
    $element->postValue(['value' => [$value]]);
  }

  /**
   * Installs the testing module for scenarios tagged with @errorPage.
   *
   * @BeforeScenario @errorPage&&@api
   */
  public function beforeErrorPageTesting(): void {
    static::toggleModule('install', ['error_page_test']);

    // Pipe error log entries to a file rather than to standard PHP log.
    static::runCommand('drupal:settings error_page_test --root=' . static::getPath('web') . ' --sites-subdir=default');
    $settings = Settings::getAll();
    $settings['error_page']['log']['method'] = 3;
    $settings['error_page']['log']['destination'] = 'php://temp';
    new Settings($settings);
  }

  /**
   * Uninstalls the testing module for scenarios tagged with @errorPage.
   *
   * @AfterScenario @errorPage&&@api
   */
  public function afterErrorPageTesting(): void {
    static::toggleModule('uninstall', ['error_page_test']);

    // Restore piping error log entries to the standard PHP log.
    $settings = Settings::getAll();
    unset($settings['error_page']['log']);
    new Settings($settings);
    static::runCommand('drupal:settings behat --root=' . static::getPath('web') . ' --sites-subdir=default');

    // Restore the site's error logging verbosity.
    $this->setSiteErrorLevel();
  }

  /**
   * Sets the site's error logging verbosity.
   *
   * @param string|null $error_level
   *   (optional) The error level. If not passed, the original error level is
   *   restored.
   *
   * @Given the site error reporting verbosity is( set to) :error_level
   */
  public function setSiteErrorLevel(?string $error_level = NULL): void {
    static $original_error_level;

    $config = \Drupal::configFactory()->getEditable('system.logging');

    $current_error_level = $config->get('error_level');
    if (!isset($original_error_level)) {
      $original_error_level = $current_error_level;
    }

    $error_level = $error_level ?: $original_error_level;
    if ($current_error_level !== $error_level) {
      static::bypassReadOnlyConfig();
      $config->set('error_level', $error_level)->save();
      static::restoreReadOnlyConfig();
    }
  }

  /**
   * Navigates to the canonical page of a taxonomy term with a given format.
   *
   * @param string $vocabulary_name
   *   The name of the vocabulary.
   * @param string $term_name
   *   The term name.
   * @param string $format
   *   The RDF serialization format.
   *
   * @Given I visit the :vocabulary_name term :term_name page in the :format serialisation
   */
  public function visitTermWithFormat(string $vocabulary_name, string $term_name, string $format): void {
    /** @var \Drupal\taxonomy\Entity\Vocabulary $vocabulary */
    $vocabulary = $this->getEntityByLabel('taxonomy_vocabulary', $vocabulary_name);
    $term = $this->getEntityByLabel('taxonomy_term', $term_name, $vocabulary->id());
    $this->visitPath($term->toUrl('canonical', ['query' => ['_format' => $format]])->toString());
  }

  /**
   * Creates testing media items.
   *
   * You need to provide media types which you want to create.
   *
   * @code
   * @generateMediaTypes:<mediaType1>,<mediaType2>,...
   * @endcode
   *
   * @BeforeScenario @generateMedia
   */
  public function generateMedia(BeforeScenarioScope $scope): void {
    $mediaTypes = $this->getTagValues($scope, 'generateMediaTypes');
    if (empty($mediaTypes)) {
      throw new \Exception('Please provide media types with generateMediaTypes:.. tag');
    }

    foreach ($mediaTypes as $mediaType) {
      $definition = \Drupal::service('entity_field.manager')
        ->getFieldDefinitions('media', $mediaType);
      for ($i = 0; $i < 10; $i++) {
        $media = Media::create([
          'bundle' => $mediaType,
          'name' => $this->getRandom()->string(),
          'image_library_widget_image' => ImageItem::generateSampleValue($definition['image_library_widget_image']),
        ]);
        $media->save();

        $this->entities['media'][$media->id()] = $media;
      }
    }
  }

  /**
   * Clears testing media items.
   *
   * @AfterScenario @generateMedia
   */
  public function clearMedia(): void {
    if (!empty($this->entities['media'])) {
      /** @var \Drupal\media\MediaStorage $media_storage */
      $media_storage = \Drupal::entityTypeManager()->getStorage('media');
      $media_storage->delete($this->entities['media']);
      unset($this->entities['media']);
    }
    \Drupal::state()->delete('image_library_widget.clicks');
  }

  /**
   * Get tag values.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope|\Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   Scenario scope.
   * @param string $prefix
   *   Tag name.
   *
   * @return array
   *   Tag values.
   */
  protected function getTagValues(BeforeScenarioScope|AfterScenarioScope $scope, string $prefix): array {
    return array_reduce(
      $scope->getScenario()->getTags(),
      function (array $values, string $tag) use ($prefix) {
        if (preg_match('/^' . $prefix . ':(.*)$/', $tag, $found)) {
          $values = explode(',', $found[1]);
        }
        return $values;
      },
      []
    );
  }

  /**
   * Creates a backup of the `VERSION.txt` file.
   *
   * Tests that interact with the version file should be tagged with
   * `@joinupVersion`.
   *
   * @BeforeScenario @joinupVersion&&@api
   */
  public function backupJoinupVersion(): void {
    $filename = JoinupVersionInterface::PATH;
    $this->version = file_exists($filename) ? file_get_contents($filename) : FALSE;
  }

  /**
   * Restores the backup of the Joinup `VERSION` file.
   *
   * @AfterScenario @joinupVersion&&@api
   */
  public function restoreJoinupVersion(): void {
    if ($this->version === FALSE) {
      unlink(JoinupVersionInterface::PATH);
    }
    else {
      file_put_contents(JoinupVersionInterface::PATH, $this->version);
    }
  }

  /**
   * Sets the Joinup version.
   *
   * Since this overwrites the `VERSION.txt` file in the webroot directory, any
   * scenario that includes this step should be tagged with `@joinupVersion` so
   * that the original contents of the file will be restored at the end of the
   * scenario.
   *
   * @param string $version
   *   The Joinup version to set, e.g. 'v1.57.0' or 'v1.57.0-66-g1234abcde'.
   *
   * @When the Joinup version is set to :version
   */
  public function setJoinupVersion(string $version): void {
    // Alert the user that the `@joinupVersion` tag is required.
    Assert::assertTrue($this->hasTag('joinupVersion'), 'The `@joinupVersion` tag is required for scenarios that want to change the Joinup version.');

    // We also require the Drupal API to retrieve the project root folder.
    Assert::assertTrue($this->hasTag('api'), 'The `@api` tag is required for scenarios that use the `@joinupVersion` tag.');

    file_put_contents(JoinupVersionInterface::PATH, $version);

    // The version string is not meant to change in between deployments, so it
    // doesn't employ a cache context. In order to make the version show up on
    // previously cached pages we need to invalidate the render cache manually.
    Cache::invalidateTags(['rendered']);
  }

  /**
   * Asserts that a file downloaded from a link contains a list of strings.
   *
   * IMPORTANT NOTE: This step definition is not performing any file access
   * check. The file content is read directly from the file system. The user
   * access to the file should be tested in separate Behat steps.
   *
   * @param string $link_label
   *   The link from where to download the file.
   * @param \Behat\Gherkin\Node\TableNode $strings_table
   *   A table with a single column. Each row contains a string.
   *
   * @throws \Exception
   *   If the link has no href attribute or the file content cannot be loaded.
   *
   * @Then the file downloaded from the :link_label link contains the following strings:
   */
  public function assertDownloadedFileContainsStrings(string $link_label, TableNode $strings_table): void {
    if (!$link = $this->getSession()->getPage()->findLink($link_label)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'Link', NULL, $link_label);
    }
    if (!$url = $link->getAttribute('href')) {
      throw new \Exception("The link '{$link_label}' misses an 'href' attribute.");
    }

    // Get the path part from the URL.
    $path = rtrim(parse_url($url, PHP_URL_PATH), '/');
    // Remove the base path.
    $path = substr($path, strlen(base_path()));

    // Drupal private file.
    if (strpos($path, 'system/files') === 0) {
      $path = Settings::get('file_private_path') . substr($path, 12);
    }
    // Webserver accessible file.
    else {
      $path = DRUPAL_ROOT . "/{$path}";
    }

    if (($content = file_get_contents($path)) === FALSE) {
      throw new \Exception("Cannot read '{$path}' file.");
    }

    if (!$content) {
      throw new \Exception("The downloaded file has no content.");
    }

    $not_found = [];
    foreach ($strings_table->getColumn(0) as $text) {
      $matches = [];
      if (preg_match('/^.*%(.*?)%.*$/', $text, $matches)) {
        $entity = $this->getEntityByLabel('rdf_entity', $matches[1]);
        $text = str_replace("%{$matches[1]}%", $entity->id(), $text);
      }

      if (strpos($content, $text) === FALSE) {
        $not_found[] = $text;
      }
    }

    if (!empty($not_found)) {
      throw new ExpectationFailedException("Following strings were not found in the downloaded file:\n- " . implode("\n- ", $not_found));
    }
  }

  /**
   * Tests that the given entity has a creation date.
   *
   * @param string $title
   *   The entity title.
   * @param string $bundle
   *   The entity bundle.
   *
   * @Given the :title :bundle has a creation date set
   */
  public function assertEntityCreationDate(string $title, string $bundle): void {
    $rdf_bundles = [
      'collection',
      'solution',
      'asset_release',
      'asset_distribution',
    ];
    $type = in_array($bundle, $rdf_bundles) ? 'rdf_entity' : 'node';
    $entity = $this->getEntityByLabel($type, $title, $bundle);

    Assert::assertNotEmpty($entity->getCreatedTime());
  }

  /**
   * Stores the ID of the latest file entity created before the scenario.
   *
   * @BeforeScenario @api
   */
  public static function storeLastFileId(): void {
    static::$lastFileId = \Drupal::database()->query("SELECT MAX(fid) FROM {file_managed}")->fetchField() ?: 0;
  }

  /**
   * Removes files created during test scenarios.
   *
   * Since Drupal 8.4.0, files that have no remaining usages are no longer
   * deleted by default, see https://www.drupal.org/node/2891902. Even the host
   * entities are deleted after test, the files attached via UI are not cleared
   * at the end of the test scenario. This might cause some scenarios, creating
   * the same file, to fail because the file will get a different, incremental,
   * file base name. Note that files created via API are handled in
   * FileTrait::cleanFiles().
   *
   * @see https://www.drupal.org/node/2891902
   * @see \Drupal\joinup\Traits\FileTrait::cleanFiles()
   *
   * @AfterScenario @api
   */
  public static function staleFilesCleanup(): void {
    $fids = \Drupal::database()->query("SELECT fid FROM {file_managed} WHERE fid > :fid", [':fid' => static::$lastFileId])->fetchCol();
    if ($fids) {
      /** @var \Drupal\file\FileStorageInterface $storage */
      $storage = \Drupal::entityTypeManager()->getStorage('file');
      $storage->delete($storage->loadMultiple($fids));
    }

    // Due to the nature of behat running everything in a single request, the
    // cache is not cleared between scenarios. This means that the cache tags
    // are not invalidated and the cache is not cleared. This causes the default
    // icons of paragraphs to be cached when the files are deleted above which
    // breaks tests trying to access paragraphs.
    \Drupal::getContainer()->get('paragraphs_type.uuid_lookup')->clear();
  }

  /**
   * Clears file entities created via UI.
   *
   * Starting with Drupal 8.4.0 files that have no remaining usages are no
   * longer deleted by default. Some scenarios are creating files by uploading
   * them to entities, in UI. Such files are not cleared after test run even the
   * host entity is explicitly deleted. A scenario trying to upload a file used
   * previously by other scenario will result in a changed file name, making it
   * very hard to perform assertions on the file name. Scenarios that are
   * uploading files should be tagged with:
   * @code
   * @uploadFiles:<filename1.ext>,<filename2.ext>,...
   * @endcode
   * For instance, a scenario tagged with @uploadFiles:logo.png will
   * clear the 2 file entities (together with the files from files system) after
   * the scenario completes.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $event
   *   The "after scenario" scope event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when an entity with a non-existing storage is passed.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if the bundle does not exist or was needed but not specified.
   *
   * @see https://www.drupal.org/node/2891902
   *
   * @AfterScenario
   */
  public function clearFiles(AfterScenarioScope $event): void {
    $files = $this->getTagValues($event, 'uploadFiles');
    if ($files) {
      $file_storage = \Drupal::entityTypeManager()->getStorage('file');
      $fids = $file_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('filename', $files, 'IN')
        ->execute();
      if ($fids) {
        $file_storage->delete($file_storage->loadMultiple($fids));
      }
    }
  }

  /**
   * Cleans the mail collector before the scenario starts.
   *
   * @BeforeScenario @api
   */
  public function cleanUpEmailCollector(): void {
    \Drupal::state()->delete('system.test_mail_collector');
  }

  /**
   * Switch to Behat specific Drupal settings during the test suite.
   *
   * @BeforeSuite
   */
  public static function addBehatSpecificDrupalSettings(): void {
    static::runCommand('drupal:settings behat --root=' . static::getPath('web') . ' --sites-subdir=default');
  }

  /**
   * Restore the original Drupal settings.
   *
   * @AfterSuite
   */
  public static function restoreDrupalSettings(): void {
    static::runCommand('drupal:settings site-clean --root=' . static::getPath('web') . ' --sites-subdir=default');
  }

  /**
   * Selects an image from the image browser of a given field and offset.
   *
   * @param string $offset
   *   An "1 based" offset of the image.
   * @param string $content_type
   *   The type of content: collection, solution, event, news.
   *
   * @throws \InvalidArgumentException
   *   When the offset is invalid or the field doesn't exist.
   *
   * @When I select image #:offset as :content_type logo
   */
  public function selectImageFromTheFieldImageLibraryBrowser(string $offset, string $content_type): void {
    $this->validateImageLibraryBrowserStepDefinitionParams($content_type, $offset);
    $map = $this->getImageLibraryBrowserMapping();

    $page = $this->getSession()->getPage();
    if (!$field = $page->findField("files[{$map[$content_type]['fields']['logo']}_0]")) {
      throw new \InvalidArgumentException("Invalid field (logo).");
    }

    // Get the field wrapper.
    $wrapper = $field->find('xpath', './ancestor::*[contains(concat(" ", @class, " "), " form-item ")][1]');
    $images = $wrapper->findAll('css', '.image-library-widget-link');

    // Convert to zero-based index.
    $delta = $offset - 1;
    if (!isset($images[$delta])) {
      throw new \InvalidArgumentException("No image at position #$offset in logo field.");
    }

    $this->scrollElementIntoView($images[$delta]);
    $images[$delta]->click();

    // Get the clicked image file ID.
    $image_classes = $images[$delta]->getAttribute('class');
    $image_classes = $image_classes ? preg_split('/\s+/', $image_classes) : [];
    foreach ($image_classes as $class) {
      if (preg_match('/^image\-(\d+)$/', $class, $found)) {
        break;
      }
    }
    $image_fid = $found[1];

    $state = \Drupal::state();
    // Save the clicked image file ID for this field for later checks.
    $clicks = $state->get('image_library_widget.clicks', []);
    $clicks[$content_type]['logo'][$offset] = $image_fid;
    $state->set('image_library_widget.clicks', $clicks);
  }

  /**
   * Asserts that a given image from the image library was added to an entity.
   *
   * @param string $content_type
   *   The type of content: collection, solution, event, news.
   * @param string $title
   *   The content title.
   * @param string $offset
   *   The "1 based" position of the image from the image library broeser.
   *
   * @throws \RuntimeException
   *   Thrown when an entity with the given $title does not exist.
   * @throws \Exception
   *   When no image of type $type has been clicked.
   *
   * @Then the :title :content_type logo is image #:offset
   */
  public function assertLogoFromTheFieldImageLibraryBrowser(string $content_type, string $title, string $offset): void {
    $this->validateImageLibraryBrowserStepDefinitionParams($content_type, $offset);
    $map = $this->getImageLibraryBrowserMapping();
    $entity = $this->getEntityByLabel($map[$content_type]['type'], $title, $content_type);
    $image_field = $entity->get($map[$content_type]['fields']['logo']);
    if ($image_field->isEmpty()) {
      throw new ExpectationFailedException("The {$title} logo field is empty but it should point to the image at position #{$offset}.");
    }

    $state = \Drupal::state();
    $clicks = $state->get('image_library_widget.clicks', []);
    if (!isset($clicks[$content_type]['logo'][$offset])) {
      throw new \Exception("The logo images should have been clicked but it was not.");
    }
    if ($clicks[$content_type]['logo'][$offset] !== $image_field->target_id) {
      throw new ExpectationFailedException("The $title logo should contain the image #$offset but it doesn't.");
    }
  }

  /**
   * Returns the mapping of logo field to their content type.
   *
   * @return array
   *   Mapping.
   */
  protected function getImageLibraryBrowserMapping(): array {
    return [
      'collection' => [
        'type' => 'rdf_entity',
        'fields' => [
          'logo' => 'field_ar_logo',
        ],
      ],
      'solution' => [
        'type' => 'rdf_entity',
        'fields' => [
          'logo' => 'field_is_logo',
        ],
      ],
      'event' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'field_event_logo',
        ],
      ],
      'news' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'field_news_logo',
        ],
      ],
      'discussion' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'field_discussion_logo',
        ],
      ],
    ];
  }

  /**
   * Validates the parameters passed to image library step definitions.
   *
   * @param string $content_type
   *   The type of content: collection, solution, event, news.
   * @param string $offset
   *   The "1 based" position of the image from the image library browser.
   *
   * @throws \InvalidArgumentException
   *   When a passed parameters is invalid.
   */
  protected function validateImageLibraryBrowserStepDefinitionParams(string $content_type, string $offset): void {
    if (!ctype_digit($offset) || $offset < 1) {
      throw new \InvalidArgumentException("Invalid image position ($offset).");
    }
    $map = $this->getImageLibraryBrowserMapping();
    if (!isset($map[$content_type])) {
      throw new \InvalidArgumentException("Invalid content type '{$content_type}'. Allowed: " . implode(', ', array_keys($map)) . '.');
    }
  }

  /**
   * Removes the first file item from a file field.
   *
   * @param string $field_name
   *   The file field label.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   When the field is not found.
   *
   * @todo Extend this step definition to handle also item with delta > 0.
   *
   * @When I remove the (first )file from (the ):field( field)
   */
  public function removeFirstFileFromField(string $field_name): void {
    $session = $this->getSession();
    $locator = '//label[text()="' . $field_name . '"]';
    if (!$label = $session->getPage()->find('xpath', $locator)) {
      throw new ElementNotFoundException($session->getDriver(), 'Label', 'xpath', $locator);
    }
    // Get the field wrapper.
    $field = $label->find('xpath', './ancestor::*[contains(concat(" ", @class, " "), " form-item ")][1]');
    $button = $field->findButton('Remove');
    $this->scrollElementIntoView($button);
    $button->press();
  }

  /**
   * Asserts that a link has an attribute set to a specific value.
   *
   * @param string $link_text
   *   The link text.
   * @param string $attribute_name
   *   The name of the attribute to assert.
   * @param string $attribute_value
   *   The value of the attribute to assert.
   *
   * @Then the :link_text link should have the :attribute_name attribute set to :attribute_value
   */
  public function givenLinkHasAttribute(string $link_text, string $attribute_name, string $attribute_value): void {
    Assert::assertNotNull($this->getSession()->getPage()->find('xpath', "//a[@$attribute_name='$attribute_value' and text()='$link_text']"));
  }

  /**
   * Asserts that an element has an empty attribute in the specific region.
   *
   * @param string $tag
   *   Tag name.
   * @param string $atr
   *   Attribute name.
   * @param string $region
   *   Region name.
   *
   * @Then I( should) see the :tag element with empty :atr attribute in the :region( region)
   */
  public function assertRegionElementEmptyAttribute(string $tag, string $atr, string $region): void {
    $region_container = $this->getRegion($region);
    $elements = $region_container->findAll('css', $tag);
    if (empty($elements)) {
      throw new \Exception(sprintf('The element "%s" was not found in the "%s" region on the page %s', $tag, $region, $this->getSession()
        ->getCurrentUrl()));
    }
    $found = FALSE;
    foreach ($elements as $element) {
      if ($element->hasAttribute($atr) && empty($element->getAttribute($atr))) {
        $found = TRUE;
        break;
      }
    }
    if (!$found) {
      throw new \Exception(sprintf('The "%s" attribute is not present on the element "%s" in the "%s" region on the page %s', $atr, $tag, $region, $this->getSession()
        ->getCurrentUrl()));
    }
  }

  /**
   * Disables cookie consent banner for all tests.
   *
   * @BeforeSuite
   */
  public static function suiteDisableCookieConsent(): void {
    touch(__DIR__ . '/../../../disable-cookie-consent');
  }

  /**
   * Re-enables cookie consent banner after testing.
   *
   * @AfterSuite
   */
  public static function suiteEnableCookieConsent(): void {
    @unlink(__DIR__ . '/../../../disable-cookie-consent');
  }

  /**
   * Enables cookie consent banner for @cookieConsent test.
   *
   * @BeforeScenario @cookieConsent&&@api
   */
  public function scenarioEnableCookieConsent(): void {
    @unlink(__DIR__ . '/../../../disable-cookie-consent');
  }

  /**
   * Disables cookie consent banner after @cookieConsent test.
   *
   * @AfterScenario @cookieConsent&&@api
   */
  public function scenarioDisableCookieConsent(): void {
    touch(__DIR__ . '/../../../disable-cookie-consent');
  }

  /**
   * Fills a given input with the current path URL.
   *
   * @param string $label
   *   The label of the input.
   * @param string $url
   *   The URL path.
   *
   * @throws \Exception
   *    Thrown when the input fill failed.
   *
   * @When I fill :label with the absolute URL :URL
   */
  public function iFillWithTheCurrentUrlPath(string $label, string $url): void {
    $this->getSession()->getPage()->fillField($label, $this->locatePath($url));
  }

  /**
   * Asserts that the current page is rendered using a certain theme.
   *
   * @param string $expected_theme_name
   *   The expected theme label.
   *
   * @Then the page should be rendered using the :expected_theme_name theme
   */
  public function assertCurrentTheme(string $expected_theme_name): void {
    $theme_list = \Drupal::service('theme_handler')->listInfo();
    $expected_theme = array_reduce(array_keys($theme_list), function (?string $expected_theme, string $theme) use ($expected_theme_name, $theme_list): ?string {
      if (!$expected_theme) {
        $info = $theme_list[$theme];
        if ($info->info['name'] === $expected_theme_name) {
          return $theme;
        }
      }
      return $expected_theme;
    }, NULL);
    if (!$expected_theme) {
      throw new \InvalidArgumentException("The '$expected_theme_name' theme doesn't exist or is not installed");
    }

    $drupal_settings = $this->getSession()->getPage()->find('xpath', '//script[@data-drupal-selector="drupal-settings-json"]');
    $actual_theme = Json::decode($drupal_settings->getText())['theme'];

    Assert::assertSame($expected_theme, $actual_theme);
  }

  /**
   * Installs/uninstalls a module in tests.
   *
   * @param string $method
   *   Either 'install' or 'uninstall'.
   * @param array $module_name
   *   The modules to be installed/uninstalled.
   */
  protected static function toggleModule(string $method, array $module_name): void {
    // Ensure that test modules are also discoverable.
    $settings = ['extension_discovery_scan_tests' => TRUE] + Settings::getAll();
    new Settings($settings);

    static::bypassReadOnlyConfig();
    \Drupal::service('module_installer')->$method($module_name);
    static::restoreReadOnlyConfig();
  }

  /**
   * Updates an entity changed time.
   *
   * @param string $label
   *   The entity label.
   * @param string $bundle
   *   The entity bundle.
   * @param string $time
   *   The time as string.
   *
   * @When I update the :label :bundle at :time
   */
  public function updateEntityAtDateTime(string $label, string $bundle, string $time): void {
    $entity_type_id = static::entityTypeAliases()[$bundle];
    $entity = $this->getEntityByLabel($entity_type_id, $label);
    if (!$entity instanceof EntityChangedInterface) {
      throw new \InvalidArgumentException("The $entity_type_id entity type is doesn't have a changed time");
    }

    $date_time = new DrupalDateTime($time);
    $entity->setChangedTime($date_time->getTimestamp())->save();
  }

  /**
   * Asserts that an entity timestamp field was just updated to current time.
   *
   * @param string $label
   *   The entity label.
   * @param string $bundle
   *   The entity bundle.
   * @param string $field_label
   *   The field label.
   *
   * @Then the :label :bundle :field_label time has just been updated
   */
  public function assertTimestampFieldUpdatedToNow(string $label, string $bundle, string $field_label): void {
    $entity_type_id = static::entityTypeAliases()[$bundle];
    $entity = $this->getEntityByLabel($entity_type_id, $label);
    $found = FALSE;
    foreach (\Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $entity->bundle()) as $field_name => $definition) {
      if ($definition->getLabel() == $field_label && $definition->getType() === 'timestamp') {
        $found = TRUE;
        break;
      }
    }
    if (!$found) {
      throw new \InvalidArgumentException("Entity type $entity_type_id doesn't have a '$field_label' field or field is not a timestamp.");
    }
    $value = (int) $entity->get($field_name)->value;

    // We accept a fuzziness of 3 seconds because of the latency of tests. It's
    // the test responsibility to set other fixed timestamps with values not
    // very close to NOW, in order to avoid collision.
    if (\Drupal::time()->getRequestTime() - $value > 3) {
      throw new ExpectationFailedException("The entity wasn't updated recently");
    }
  }

  /**
   * Asserts that a specific entity field is matching a given regexp pattern.
   *
   * @param string $bundle
   *   The human-readable type according to self::bundleAliases().
   * @param string $title
   *   The entity title.
   * @param string $field_label
   *   The human-readable field name.
   * @param string $pattern
   *   The regexp pattern.
   * @param string $paragraph_tree
   *   Applies only the $field_label is exposed on a nested paragraph. A string
   *   like '2nd Body, 1st Accordion item, 1st Item content' where the sequence,
   *   delimited by comma, is the path down to the paragraph entity on which we
   *   want to check the $field_label field. Each item consist on human-readable
   *   delta (1-based) and the reference field label. Defaults to empty string.
   *
   * @Then the :field_label field of :title :bundle should match :pattern
   * @Then the :field_label field of :paragraph_tree paragraph of :title :bundle should match :pattern
   */
  public function assertEntityFieldMatches(string $bundle, string $title, string $field_label, string $pattern, string $paragraph_tree = ''): void {
    if (!$this->entityFieldMatchHelper($bundle, $title, $field_label, $pattern, $paragraph_tree)) {
      throw new ExpectationFailedException("The '$field_label' of $title' $bundle doesn't match the given regexp pattern but it should");
    }
  }

  /**
   * Asserts that a specific entity field doesn't match a regexp pattern.
   *
   * @param string $bundle
   *   The human-readable type according to self::bundleAliases().
   * @param string $title
   *   The entity title.
   * @param string $field_label
   *   The human-readable field name.
   * @param string $pattern
   *   The regexp pattern.
   * @param string $paragraph_tree
   *   Applies only the $field_label is exposed on a nested paragraph. A string
   *   like '2nd Body, 1st Accordion item, 1st Item content' where the sequence,
   *   delimited by comma, is the path down to the paragraph entity on which we
   *   want to check the $field_label field. Each item consist on human-readable
   *   delta (1-based) and the reference field label. Defaults to empty string.
   *
   * @Then the :field_label field of :title :bundle should not match :pattern
   * @Then the :field_label field of :paragraph_tree paragraph of :title :bundle should not match :pattern
   */
  public function assertEntityFieldNotMatch(string $bundle, string $title, string $field_label, string $pattern, string $paragraph_tree = ''): void {
    if ($this->entityFieldMatchHelper($bundle, $title, $field_label, $pattern, $paragraph_tree)) {
      throw new ExpectationFailedException("The '$field_label' of $title' $bundle matches the given regexp pattern but it should not");
    }
  }

  /**
   * Provides a helper for entity field content match assertions.
   *
   * @param string $bundle
   *   The human-readable type according to self::entityTypeAliases().
   * @param string $title
   *   The entity title.
   * @param string $field_label
   *   The human-readable field name.
   * @param string $pattern
   *   The regexp pattern.
   * @param string $paragraph_tree
   *   Applies only the $field_label is exposed on a nested paragraph. A string
   *   like '2nd Body, 1st Accordion item, 1st Item content' where the sequence,
   *   delimited by comma, is the path down to the paragraph entity on which we
   *   want to check the $field_label field. Each item consist on human-readable
   *   delta (1-based) and the reference field label. Defaults to empty string.
   */
  protected function entityFieldMatchHelper(string $bundle, string $title, string $field_label, string $pattern, string $paragraph_tree): bool {
    $entity_type_id = static::entityTypeAliases()[$bundle];
    $entity = $this->getEntityByLabel($entity_type_id, $title);

    $trail = array_map(function (string $part): array {
      [$position, $paragraph_field] = preg_split('#\s+#', trim($part), 2);
      // Convert delta from human-readable to 0-based integers.
      return [$paragraph_field, $this->convertOrdinalToNumber($position) - 1];
    }, array_filter(explode(',', $paragraph_tree)));

    // Append the field we want to check.
    $trail[] = [$field_label, 0];

    do {
      [[$field_label, $delta]] = array_splice($trail, 0, 1);
      $found = FALSE;
      foreach ($entity->getFields(FALSE) as $field_name => $field) {
        $definition = $field->getFieldDefinition();
        if ($definition->getLabel() === $field_label) {
          $found = TRUE;
          break;
        }
      }
      if (!$found) {
        throw new \LogicException("The {$entity->bundle()} {$entity->getEntityTypeId()} doesn't have a $field_label field");
      }

      if ($definition->getType() === 'entity_reference_revisions') {
        $entity = $field->get($delta)->entity;
        \assert($entity instanceof ParagraphInterface);
      }
    } while ($trail);

    $column = $definition->getFieldStorageDefinition()->getMainPropertyName();
    $value = (string) $entity->get($field_name)->{$column};
    return (bool) preg_match($pattern, $value);
  }

  /**
   * Process a queue worker.
   *
   * @param string $queue_name
   *   The queue name.
   *
   * @When I run the :queue_name queue worker
   */
  public function iRunTheQueueWorker(string $queue_name): void {
    // Create a new Process instance to run the Drush command.
    $process = Process::fromShellCommandline(
      command: sprintf('composer exec drush queue:run %s', $queue_name),
      cwd: getenv('DDEV_COMPOSER_ROOT') ?: dirname(DRUPAL_ROOT),
      timeout: 1800
    );
    $process->run();

    if (!$process->isSuccessful()) {
      throw new \RuntimeException(sprintf('Failed to execute the queue worker: %s', $process->getErrorOutput()));
    }

    sleep(1);
  }

}
