<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Element\NodeElement;
use Behat\Mink\Element\TraversableElement;
use Drupal\editor\Entity\Editor;
use Drupal\joinup\Exception\WysiwygEditorNotFoundException;
use PHPUnit\Framework\Assert;

/**
 * Helper methods for interacting with WYSIWYG editors.
 */
trait WysiwygTrait {

  use JavascriptTrait;

  /**
   * Presses the given WYSIWYG button.
   *
   * @param string $label
   *   The field label of the field to which the WYSIWYG editor is attached. For
   *   example 'Body'.
   * @param string $button
   *   The title of the button to click.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region where the editor is expected to be located.
   *   Defaults to the entire page.
   *
   * @throws \Exception
   *   Thrown when the button is not found, or if there are multiple buttons
   *   with the same title.
   */
  public function pressWysiwygButton(string $label, string $button, ?TraversableElement $region = NULL): void {
    $buttonElement = $this->getWysiwygButton($label, $button, $region);
    Assert::assertInstanceOf(
      NodeElement::class,
      $buttonElement,
      sprintf(
        "Could not find the '%s' button.",
        $button,
      )
    );
    $buttonElement->press();
  }

  /**
   * Determines whether the given WYSIWYG button exists.
   *
   * @param string $label
   *   The label of the field with the editor.
   * @param string $button
   *   The title of the button to look for.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   The region where the editor is expected to be located.
   *
   * @return \Behat\Mink\Element\NodeElement|null
   *   The WYSIWYG button element if it exists, or NULL if it is missing.
   */
  protected function getWysiwygButton(string $label, string $button, ?TraversableElement $region = NULL): ?NodeElement {
    $wysiwyg = $this->getWysiwyg($label, $region);
    $matchingButtons = $wysiwyg->findAll('css', 'button[data-cke-tooltip-text*="' . $button . '"]');
    if (empty($matchingButtons)) {
      return NULL;
    }

    Assert::assertCount(
      1,
      $matchingButtons,
      sprintf(
        "Multiple '%s' buttons found in the editor.",
        $button,
      ),
    );
    return current($matchingButtons);
  }

  /**
   * Enters the given text in the textarea of the specified WYSIWYG editor.
   *
   * If there is any text existing it will be replaced.
   *
   * @param string $label
   *   The field label of the field to which the WYSIWYG editor is attached. For
   *   example 'Body'.
   * @param string $text
   *   The text to enter in the textarea.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region where the editor is expected to be located.
   *   Defaults to the entire page.
   *
   * @throws \Exception
   *   Thrown when the textarea could not be found.
   */
  public function setWysiwygText(string $label, string $text, ?TraversableElement $region = NULL): void {
    $wysiwyg = $this->getWysiwyg($label, $region);

    $wrapper_id = $wysiwyg->getAttribute('id');
    $ckeditor5_drupal_editable_element = "div#$wrapper_id .ck-editor__editable";
    $function = <<<JS
  (
      function(){
        const domEditableElement = document.querySelector("$ckeditor5_drupal_editable_element");
        if (domEditableElement.ckeditorInstance) {
          const editorInstance = domEditableElement.ckeditorInstance;
          if (editorInstance) {
            editorInstance.setData("$text");
          } else {
            throw new Exception('Could not get the editor instance!');
          }
        } else {
          throw new Exception('Could not find the element!');
        }
      }
  )()
  JS;
    try {
      $this->getSession()->executeScript($function);
      // Some elements can trigger AJAX (e.g.: Media preview).
      $this->waitForAjax();
    }
    catch (\Exception $e) {
      throw new \Exception("Could not fill the textarea for the '$label' field.");
    }
  }

  /**
   * Checks whether a WYSIWYG editor with the given field label is present.
   *
   * @param string $label
   *   The label of the field to which the WYSIWYG editor is attached.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region where the editor is expected to be located.
   *   Defaults to the entire page.
   *
   * @return bool
   *   TRUE if the editor is present, FALSE otherwise.
   *
   * @throws \Exception
   *   Thrown when the field label or editor is present more than once.
   */
  public function hasWysiwyg(string $label, ?TraversableElement $region = NULL): bool {
    try {
      $this->getWysiwyg($label, $region);
      return TRUE;
    }
    // Only catch the specific exception that is thrown when the WYSIWYG editor
    // is not present, let all other exceptions pass through.
    catch (WysiwygEditorNotFoundException $e) {
      return FALSE;
    }
  }

  /**
   * Returns the WYSIWYG editor that is associated with the given field label.
   *
   * This is hardcoded on the CKE editor which is included with Drupal core.
   *
   * @param string $label
   *   The label of the field to which the WYSIWYG editor is attached.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region where the editor is expected to be located.
   *   Defaults to the entire page.
   *
   * @return \Behat\Mink\Element\TraversableElement
   *   The WYSIWYG editor.
   *
   * @throws \Drupal\joinup\Exception\WysiwygEditorNotFoundException
   *   Thrown when the field label or wysiwyg editor is not found.
   */
  protected function getWysiwyg(string $label, ?TraversableElement $region = NULL): TraversableElement {
    if (empty($region)) {
      $region = $this->getSession()->getPage();
    }

    $label_elements = $region->findAll('named', ['field', $label]);
    if (empty($label_elements)) {
      throw new WysiwygEditorNotFoundException("Could not find the '$label' field label.");
    }

    if (count($label_elements) > 1) {
      throw new \Exception("Found multiple instances of the '$label' field label.");
    }

    $label_element = reset($label_elements);

    $wysiwyg_elements = $label_element->findAll('xpath', '/ancestor::*[contains(concat(" ", normalize-space(@class), " "), " form-wrapper ")][position() = 1]');
    if (empty($wysiwyg_elements)) {
      throw new WysiwygEditorNotFoundException("Could not find the '$label' wysiwyg editor.");
    }

    if (count($wysiwyg_elements) > 1) {
      throw new \Exception("Found multiple wysiwyg editors with the '$label' field label.");
    }

    return reset($wysiwyg_elements);
  }

  /**
   * Returns the Editor entity that is associated with the given field label.
   *
   * @param string $label
   *   The label of the field to which the WYSIWYG editor is attached.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region where the editor is expected to be located.
   *   Defaults to the entire page.
   *
   * @return \Drupal\editor\Entity\Editor
   *   The Editor entity.
   *
   * @throws \Drupal\joinup\Exception\WysiwygEditorNotFoundException
   *   Thrown when the field label or wysiwyg editor is not found.
   */
  protected function getWysiwygEditorEntity(string $label, ?TraversableElement $region = NULL): Editor {
    if (empty($region)) {
      $region = $this->getSession()->getPage();
    }

    // Find the hidden editor select input element.
    $editor_select_element = $region->find('xpath', '//textarea[@id=//label[text()="' . $label . '"]/@for]/ancestor::*[contains(concat(" ", normalize-space(@class), " "), " form-wrapper ")][position() = 1]//input[@type = "hidden"]');
    if (!$editor_select_element instanceof NodeElement) {
      throw new WysiwygEditorNotFoundException("Could not find the '$label' wysiwyg editor.");
    }

    $editor_id = $editor_select_element->getValue();
    if (empty($editor_id)) {
      throw new WysiwygEditorNotFoundException("Could not find the machine name of the '$label' wysiwyg editor.");
    }
    $editor = Editor::load($editor_id);
    if (!$editor instanceof Editor) {
      throw new WysiwygEditorNotFoundException("Could not load the editor entity for the '$label' wysiwyg editor.");
    }

    return $editor;
  }

  /**
   * Returns the buttons that are shown in the editor with the given label.
   *
   * Note that this returns different labels depending on whether or not the
   * browser supports JavaScript. For example a non-JS browser will return
   * 'Blockquote' while a JS browser will return 'Block Quote'.
   *
   * @param string $label
   *   The label of the field to which the WYSIWYG editor is attached.
   * @param \Behat\Mink\Element\TraversableElement|null $region
   *   (optional) The region where the editor is expected to be located.
   *   Defaults to the entire page.
   *
   * @return string[]
   *   An array of button labels.
   *
   * @throws \Drupal\joinup\Exception\WysiwygEditorNotFoundException
   *   Thrown when the WYSIWYG editor with the given label is not found in the
   *   page.
   */
  protected function getWysiwygButtons(string $label, ?TraversableElement $region = NULL): array {
    assert(method_exists($this, 'hasJavascriptSupport'), __METHOD__ . ' depends on JavascriptTrait. Please include it in your class.');

    if ($this->hasJavascriptSupport()) {
      $editor = $this->getWysiwyg($label, $region);
      $button_elements = $editor->findAll('xpath', '//button[contains(concat(" ", normalize-space(@class), " "), " ck-button ")]');

      $buttons = array_map(function (NodeElement $element): string {
        $text = $element->getAttribute('data-cke-tooltip-text') ?? $element->getText();
        return trim($text);
      }, $button_elements);
    }
    else {
      // When no JS is available the editor is not loaded in the page. Retrieve
      // the buttons from the editor config.
      /** @var \Drupal\ckeditor5\Plugin\CKEditor5PluginManager $cke5_plugin_manager */
      $cke5_plugin_manager = \Drupal::service('plugin.manager.ckeditor5.plugin');
      $editor = $this->getWysiwygEditorEntity($label);

      $settings = $editor->getSettings();

      // Retrieve the buttons that are available for this editor instance, but
      // filter out the separators. We are only concerned with the buttons.
      $buttons = array_filter($settings['toolbar']['items'], function (string $id): bool {
        return $id !== '|';
      });

      // Replace the button IDs with human readable labels.
      $button_data = $cke5_plugin_manager->getToolbarItems();
      $buttons = array_map(function (string $id) use ($button_data) {
        return (string) $button_data[$id]['label'];
      }, $buttons);
    }

    return $buttons;
  }

}
