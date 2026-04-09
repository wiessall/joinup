<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\NameMappingsTrait;
use WebDriver\Element;

/**
 * Behat step definitions that are generally useful for the Joinup project.
 */
class TagifyContext extends RawDrupalContext {

  use JavascriptTrait;
  use NameMappingsTrait;

  /**
   * Fills tagify autocomplete.
   *
   * @param string $tags
   *   A keywords to input.
   * @param string $fieldName
   *   A field name of tagify autocomplete.
   *
   * @throws \Exception
   *   Thrown when the tagify field is not found.
   */
  #[When('I select :tags option(s) from :fieldName')]
  public function selectTagsFromTagify(string $tags, string $fieldName): void {
    if ($this->hasJavascriptSupport()) {
      $this->selectFromTagifyWithJs($tags, $fieldName);
    }
    else {
      $this->selectFromTagifyWithoutJs($tags, $fieldName);
    }
  }

  /**
   * Fills tagify autocomplete using Javascript.
   *
   * @param string $tags
   *   A keywords to input.
   * @param string $fieldName
   *   A field name of tagify autocomplete.
   *
   * @throws \Exception
   *   Thrown when the tagify field is not found.
   */
  protected function selectFromTagifyWithJs(string $tags, string $fieldName): void {
    $tagifyInput = $this->getTagifyInput($fieldName);
    $element = $this->getSession()->getDriver()->getWebDriverSession()?->element('xpath', $tagifyInput->getXpath());
    \assert($element instanceof Element);

    $explodedTags = array_map('trim', explode(',', $tags));
    foreach ($explodedTags as $tag) {
      // \Behat\Mink\Driver\Selenium2Driver::setValue() will call .blur() on
      // the element, which does not play well with tagify.
      // Use postValue directly and blur after selecting all values, triggering
      // the change event.
      $element->postValue(['text' => $tag]);
      $dropdownItem = $this->waitForElementVisible('css', '.tagify__dropdown__item:contains("' . $tag . '")');
      $dropdownItem->click();
    }
    $this->getSession()->getDriver()->blur($tagifyInput->getXpath());
  }

  /**
   * Fills tagify autocomplete without Javascript.
   *
   * @param string $tags
   *   A tags to input.
   * @param string $fieldName
   *   A field name of tagify autocomplete.
   *
   * @throws \Exception
   *   Thrown when the tagify field is not found.
   */
  protected function selectFromTagifyWithoutJs(string $tags, string $fieldName): void {
    $tags = explode(',', $tags);
    $field = $this->getSession()->getPage()->findField($fieldName);
    if (!$field) {
      throw new \Exception("The field '$field' not found in the page.");
    }

    $currentValueRaw = $field->getValue();
    $currentValue = $currentValueRaw
      ? json_decode($currentValueRaw, TRUE)
      : [];
    $field->setValue(json_encode(array_merge($currentValue, $this->prepareTagArray($tags))));
  }

  /**
   * Returns array of tags.
   *
   * @param array $tags
   *   Array of tags.
   *
   * @return array
   *   Array of tags to json encoding.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function prepareTagArray(array $tags): array {
    $tagJson = [];

    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $query = $storage->getQuery()
      ->condition('name', $tags, 'IN')
      ->accessCheck(TRUE);
    $tids = $query->execute();
    foreach ($storage->loadMultiple($tids) as $term) {
      $tagJson[] = [
        'value' => $term->id(),
        'label' => $term->label(),
        'entity_id' => $term->id(),
        'info-label' => '',
        'editable' => FALSE,
      ];
    }
    $foundTags = array_map(
      fn (array $item): string => (string) $item['label'],
      $tagJson,
    );
    $tagJson = array_reduce(
      $tags,
      function (array $tagJson, $tagToCheck) use ($foundTags): array {
        if (!in_array($tagToCheck, $foundTags, TRUE)) {
          $tagJson[] = [
            'value' => $tagToCheck,
            'label' => $tagToCheck,
          ];
        }
        return $tagJson;
      },
      $tagJson,
    );
    return $tagJson;
  }

  /**
   * Remove options from tagify autocomplete.
   *
   * @param string $tags
   *   A tags to input.
   * @param string $fieldName
   *   A field name of tagify autocomplete.
   *
   * @throws \Exception
   *   Thrown when the tagify field is not found.
   */
  #[When('I remove :tags options from :fieldName')]
  public function removeTagsFromTagify(string $tags, string $fieldName): void {
    if (!$this->hasJavascriptSupport()) {
      throw new \Exception('This step requires JavaScript to run.');
    }
    $tags = explode(',', $tags);
    foreach ($tags as $tag) {
      $locator = sprintf('.%s tag.tagify__tag:contains("%s")', $this->getNodeFieldNameFromLabel($fieldName), $tag);
      $tag = $this->getSession()->getPage()->find('css', $locator);
      if (empty($tag)) {
        throw new \Exception('Tag "' . $tag . '" not found');
      }
      $removeButton = $tag->find('css', '#tagify__tag-remove-button');
      if (empty($removeButton)) {
        throw new \Exception('Remove button for tag "' . $tag . '" not found');
      }
      $removeButton->click();
    }
  }

  /**
   * Checks if options are visible in tagify dropdown options.
   *
   * @param string $fieldName
   *   Field name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Table of expected dropdown options.
   *
   * @throws \Exception
   *   Thrown when the tagify option is not found.
   */
  #[When('the :fieldName tagify autocomplete should contain the following options:')]
  public function checkTagifyOptions(string $fieldName, TableNode $table): void {
    if (!$this->hasJavascriptSupport()) {
      throw new \Exception('This step requires JavaScript to run.');
    }
    $tagifyInput = $this->getTagifyInput($fieldName);
    foreach ($table->getRows() as $row) {
      $option = reset($row);
      $tagifyInput->setValue($option);
      $dropdownItem = $this->waitForElementVisible('css', '.tagify__dropdown__item:contains("' . $option . '")');
      if (empty($dropdownItem)) {
        throw new \Exception('Option "' . $option . '" not found');
      }
    }
  }

  /**
   * Checks if options are not visible in tagify dropdown options.
   *
   * @param string $fieldName
   *   Field name.
   * @param string $option
   *   Option that should NOT be present in the dropdown.
   *
   * @throws \Exception
   *   Thrown when the tagify option is not found.
   */
  #[When('the :fieldName tagify should not contain the following option :option')]
  public function checkTagifyOptionsAreNotPresent(string $fieldName, string $option): void {
    if (!$this->hasJavascriptSupport()) {
      throw new \Exception('This step requires JavaScript to run.');
    }
    $tagifyInput = $this->getTagifyInput($fieldName);
    $tagifyInput->setValue($option);
    $dropdownItem = $this->waitForElementVisible('css', '.tagify--dropdown-item-no-match:contains("' . $option . '")');
    if (empty($dropdownItem)) {
      throw new \Exception('Option "' . $option . '" found');
    }
  }

  /**
   * Checks if option groups are visible in tagify dropdown options.
   *
   * @param string $fieldName
   *   Field name.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   Table of expected dropdown options.
   *
   * @throws \Exception
   *   Thrown when the tagify option group is not found.
   */
  #[When('the :fieldName tagify autocomplete should contain the following option groups:')]
  public function checkTagifyOptionGroups(string $fieldName, TableNode $table): void {
    if (!$this->hasJavascriptSupport()) {
      throw new \Exception('This step requires JavaScript to run.');
    }
    $tagifyInput = $this->getTagifyInput($fieldName);
    $tagifyInput->click();
    $dropdown = $this->waitForElement('css', '.tagify__dropdown');
    foreach ($table->getRows() as $row) {
      $optionGroup = reset($row);
      $dropdownItem = $dropdown->find('css', '.dropdown_group:contains("' . $optionGroup . '")');
      if (empty($dropdownItem)) {
        throw new \Exception('Option group ' . $optionGroup . ' not found');
      }
    }
  }

  /**
   * Opens the tagify dropdown by clicking the input and waits for it to appear.
   *
   * @param string $fieldName
   *   Field label.
   *
   * @throws \Exception
   *   Thrown when the dropdown does not appear.
   */
  #[When('I open the :fieldName tagify dropdown')]
  public function openTagifyDropdown(string $fieldName): void {
    $this->getTagifyInput($fieldName)->click();
    $this->waitForElement('css', '.tagify__dropdown');
  }

  /**
   * Gets tagify input element.
   *
   * @param string $fieldName
   *   Field name.
   *
   * @return \Behat\Mink\Element\NodeElement|mixed
   *   Tagify input element.
   *
   * @throws \Exception
   */
  protected function getTagifyInput(string $fieldName): NodeElement {
    if (!$this->hasJavascriptSupport()) {
      throw new \Exception('This function requires JavaScript to run.');
    }
    $session = $this->getSession();
    $fieldName = str_replace('_', '-', $this->getNodeFieldNameFromLabel($fieldName));
    $locator = '.field--name-' . $fieldName . ' .tagify__input';
    $tagifyInput = $session->getPage()->find('css', $locator);
    if (!$tagifyInput) {
      throw new \Exception('Tagify input not found');
    }
    return $tagifyInput;
  }

}
