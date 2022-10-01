<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\TraversableElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Behat step definitions related to file fields.
 */
class FileFieldContext extends RawDrupalContext {

  use UtilityTrait;

  /**
   * Fills in a specific subfield of a given file field.
   *
   * Use sequence number for the delta.
   * @codingStandardsIgnoreStart
   * Example:
   * - Given I fill in the 2nd "Description" field of the "Attachment" file field with "My description"
   * @codingStandardsIgnoreEnd
   *
   * @param string $field
   *   The file field name.
   * @param string $subfield
   *   The subfield name.
   * @param string $value
   *   The value to set.
   * @param string $delta
   *   The delta of the field.
   *
   * @Given I fill in the :delta :subfield field of the :field file field with :value
   */
  public function fillInSubfieldInFileField(string $field, string $subfield, string $value, string $delta): void {
    $file_field = $this->findSubFieldOfFileField($field);
    $delta = $this->convertOrdinalToNumber($delta) - 1;
    $fields = $file_field->findAll('named_exact', ['field', $subfield]);
    if (!isset($fields[$delta])) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'Subfield not found', $subfield);
    }

    $fields[$delta]->setValue($value);
  }

  /**
   * Checks or unchecks a checkbox field in a file field.
   *
   * Use sequence number for the delta.
   * @codingStandardsIgnoreStart
   * Example:
   * - Given I check in the 2nd "Display" field of the "Attachment" file field
   * - Given I uncheck in the 2nd "Display" field of the "Attachment" file field
   * @codingStandardsIgnoreEnd
   *
   * @param string $action
   *   Whether to check or uncheck the checkbox.
   * @param string $field
   *   The file field name.
   * @param string $subfield
   *   The subfield name.
   * @param string $delta
   *   The delta of the field.
   *
   * @Given I :action the :delta :subfield checkbox of the :field file field
   */
  public function changeCheckboxOfFileField(string $action, string $field, string $subfield, string $delta): void {
    assert(in_array($action, ['check', 'uncheck']), 'The action should be either "check" or "uncheck".');
    $file_field = $this->findSubFieldOfFileField($field);
    $delta = $this->convertOrdinalToNumber($delta) - 1;
    $subfield = strtolower($subfield);
    $field = $file_field->find('xpath', "//input[@type='checkbox' and contains(@name, '[{$delta}][{$subfield}]')]");
    if (empty($field)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'Subfield not found', $subfield);
    }

    if ($action === 'check' && $field->isChecked() || $action === 'uncheck' && !$field->isChecked()) {
      throw new \Exception("The checkbox is already {$action}ed.");
    }
    $action === 'check' ? $field->check() : $field->uncheck();
  }

  /**
   * Returns a file field element.
   *
   * @param string $field
   *   The file field name.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The file field node element.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Thrown when the element is not found.
   */
  protected function findSubFieldOfFileField(string $field): TraversableElement {
    $xpath = "//div[contains(concat(' ', @class, ' '), ' field--type-file ') and .//summary[contains(., '{$field}')]]";
    $element = $this->getSession()->getPage()->find('xpath', $xpath);
    if (empty($element)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'File field not found', $field);
    }
    return $element;

  }

}
