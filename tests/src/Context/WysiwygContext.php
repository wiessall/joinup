<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup\Traits\WysiwygTrait;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for interacting with WYSIWYG editors.
 */
class WysiwygContext extends RawDrupalContext {

  use JavascriptTrait;
  use UtilityTrait;
  use WysiwygTrait;

  /**
   * Enters the given text in the given WYSIWYG editor.
   *
   * If this is running on a JavaScript enabled browser it will first click the
   * 'Source' button so the text can be entered as normal HTML.
   *
   * @param string $text
   *   The text to be entered in the WYSIWYG editor.
   * @param string $label
   *   The label of the field containing the WYSIWYG editor.
   *
   * @When I enter :text in the :label wysiwyg editor
   */
  public function enterTextInWysiwyg(string $text, string $label): void {
    // If we are running in a JavaScript enabled browser, first click the
    // 'Source' button so we can enter the text as HTML and get the same result
    // as in a non-JS browser.
    if ($this->hasJavascriptSupport()) {
      $this->pressWysiwygButton($label, 'Source');
      $this->setWysiwygText($label, $text);
    }
    else {
      $this->getSession()->getPage()->fillField($label, $text);
    }
  }

  /**
   * Presses a button in a given WYSIWYG editor.
   *
   * @param string $button
   *   The label of the button to press.
   * @param string $label
   *   The label of the field containing the WYSIWYG editor.
   *
   * @Then I press the button :button in the :label wysiwyg editor
   */
  public function pressButtonInWysiwyg(string $button, string $label): void {
    self::assertJavaScriptEnabledBrowser();
    $this->pressWysiwygButton($label, $button);
  }

  /**
   * Checks that a given field label is associated with a WYSIWYG editor.
   *
   * @param string $label
   *   The label of the field containing the WYSIWYG editor.
   *
   * @Then I should see the :label wysiwyg editor
   */
  public function assertWysiwyg(string $label): void {
    Assert::assertTrue($this->hasWysiwyg($label));
  }

  /**
   * Asserts that a ckeditor list contains an element.
   *
   * CKeditor stores the lists available in the header of the editor pane in an
   * iframe with a role tag. The dropdown "Format" has to be clicked prior to
   * having the iframe available.
   *
   * @param string $label
   *   The name of the field that contains the Wysiwyg editor to check.
   * @param string $format_tags
   *   Comma-separated list of paragraph formats to check.
   *
   * @throws \Exception
   *   Thrown when the formats were found in the format list.
   *
   * @Then the paragraph formats in the :field field should not contain the :format_tags format(s)
   */
  public function assertNotFormatInCkeditorExists(string $label, string $format_tags): void {
    if (!$this->hasJavascriptSupport()) {
      throw new \Exception('This step requires javascript to run.');
    }

    $headings = [
      'h1' => 'heading1',
      'h2' => 'heading2',
      'h3' => 'heading3',
      'h4' => 'heading4',
      'h5' => 'heading5',
      'h6' => 'heading6',
    ];
    $format_tags = $this->explodeCommaSeparatedStepArgument($format_tags);

    $editor = $this->getWysiwygEditorEntity($label);
    $settings = $editor->getSettings();
    $enabled_headings = $settings['plugins']['ckeditor5_heading']['enabled_headings'];
    $found_tags_array = array_diff($headings, $enabled_headings);
    $found_tags = array_keys($found_tags_array);

    $invalid_tags = array_diff_key($format_tags, $found_tags);
    if (!empty($invalid_tags)) {
      throw new \Exception(sprintf('The following elements were found in the format list but should not: %s.', implode(', ', $invalid_tags)));
    }
  }

  /**
   * Checks that a WYSIWYG editor has the correct buttons.
   *
   * This works by discovering the editor type in the HTML, and then loading the
   * configuration from the database. This makes sure it also works in non-JS
   * browsers.
   *
   * @param string $label
   *   The label of the field containing the WYSIWYG editor to inspect.
   * @param string $buttons
   *   A comma-separated list of buttons that should be present, in the correct
   *   order.
   *
   * @Then the :label wysiwyg editor should have the buttons :buttons
   */
  public function assertWysiwygButtons(string $label, string $buttons): void {
    $actual_buttons = $this->getWysiwygButtons($label);
    $expected_buttons = array_map('trim', explode(',', $buttons));
    Assert::assertEquals(array_values($expected_buttons), array_values($actual_buttons));
  }

  /**
   * Asserts that a given WYSIWYG editor contains a certain button.
   *
   * @param string $label
   *   The label of the field containing the WYSIWYG editor to inspect.
   * @param string $button
   *   The button label.
   *
   * @Given the :button button should be available in the :label wysiwyg editor
   */
  public function wysiwygContainsButton(string $label, string $button): void {
    $available_buttons = $this->getWysiwygButtons($label);
    if (!in_array($button, $available_buttons)) {
      throw new \Exception("No '{$button}' button was found in the '$label' editor.");
    }
  }

  /**
   * Checks if the WYSIWYG editor height is correct.
   *
   * @param string $label
   *   The label of the field containing the WYSIWYG editor to inspect.
   *
   * @Then I check :label wysiwyg height
   */
  public function checkWysiwygHeight(string $label): void {
    $wysiwyg = $this->getWysiwyg($label);
    $ck_content = $wysiwyg->find('css', '.ck-content')->getXpath();
    $wysiwyg_height = $this->getSession()->evaluateScript('return document.evaluate(`' . $ck_content . '`, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue.offsetHeight;');
    $body_height = $this->getSession()->evaluateScript('return Math.max(
            document.body.scrollHeight,
            document.body.offsetHeight,
            document.documentElement.offsetHeight
        );');
    if ($wysiwyg_height > $body_height) {
      throw new \Exception("'$label' editor is displayed with wrong height.");
    }
  }

}
