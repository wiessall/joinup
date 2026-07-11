<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Then;
use Drupal\Component\Utility\Variable;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AssertTrait;

/**
 * Behat step definitions for testing Webtools eTrans translatable text.
 */
class JoinupEtransContext extends RawDrupalContext {

  use AssertTrait;

  /**
   * Verifies that the given text is translatable by eTrans service.
   */
  #[Then('the eTrans translatable text should contain the following text:')]
  public function eTransTextMatches(TableNode $table): void {
    $expectedLines = $table->getColumn(0);
    $translatableText = $this->getTranslatableText();
    $actual = explode(PHP_EOL, $translatableText);
    $this->assertCondition(
      empty(array_diff($expectedLines, $actual)),
      sprintf(
        "Translatable text does not contain all the expected lines.\nExpected lines:\n%s\nActual lines:\n%s ",
        Variable::export($expectedLines),
        Variable::export($actual)
      )
    );
  }

  /**
   * Returns text from the page that can be translated by OE Webtools eTrans.
   *
   * @return string
   *   The text from the page that can be translated by OpenEuropa Webtools
   *   eTransate service. The string can contain line breaks.
   */
  protected function getTranslatableText(): string {
    $js = <<<'JS'
(function getSelectors() {
  let includedSelectors = '';
  let excludedSelectors = '';
  const inlineElements = [
    "#text",
    "A",
    "ABBR",
    "ACRONYM",
    "B",
    "BDO",
    "BIG",
    // Exclude <br> since it represents a line break.
    "BUTTON",
    "CITE",
    "CODE",
    "DFN",
    "EM",
    "I",
    "IMG",
    "INPUT",
    "KBD",
    "LABEL",
    "MAP",
    "OBJECT",
    "OUTPUT",
    "Q",
    "SAMP",
    "SCRIPT",
    "SELECT",
    "SMALL",
    "SPAN",
    "STRONG",
    "SUB",
    "SUP",
    "TEXTAREA",
    "TIME",
    "TT",
    "VAR",
  ];

  document.querySelectorAll("body script[type='application/json']").forEach((element) => {
    const json = JSON.parse(element.textContent);
    if (!json.service || json.service !== "etrans") {
      return;
    }
    const { include, exclude } = json;
    if (include) {
      includedSelectors += includedSelectors
        ? `,${include}`
        : include;
    }
    if (exclude) {
      excludedSelectors += excludedSelectors
        ? `,${exclude}`
        : exclude;
    }
  });

  /**
   * Returns a node element without the elements which should be excluded.
   *
   * @param {Element} node
   *   The element which should be translatable.
   * @return {Element}
   *   A new node element without the elements which should be excluded.
   */
  const getCleanedElement = (node) => {
    const element = node.cloneNode(true);
    if (excludedSelectors) {
      element
        .querySelectorAll(excludedSelectors)
        .forEach((elem) => {
          elem.remove();
        });
    }
    return element;
  };

  /**
   * Returns the cleaned text content of the given element.
   *
   * @param {Element} element
   *   The element to get the text content of.
   * @return {string}
   *   The cleaned text content of the given element.
   */
  const getCleanedText = (element) => {
    const text = [];
    let textcontent = "";
    let current = 0;
    let isInline = false;
    element.childNodes.forEach((child) => {
      isInline = inlineElements.includes(child.nodeName);
      if (isInline) {
        textcontent = child.textContent.replace(/\n+/, " ");
      } else {
        textcontent = `\n${getCleanedText(child)}\n`;
      }
      if (textcontent.trim()) {
        current += !isInline ? 1 : 0;
        text[current] = (text[current] ?? "") + textcontent;
      }
    });

    return (
      text
        .join("\n")
        // Replace long whitespaces.
        .replace(
          /[ \f\r\t\v\u00a0\u1680\u2000-\u200a\u2028\u2029\u202f\u205f\u3000\ufeff]{2,}/g,
          " "
        )
        // Replace line breaks and their leading / trailing whitespaces.
        .replace(/ ?\n+ ?/g, "\n")
    );
  };

  if (!includedSelectors) {
    return "";
  }
  let text = "";
  document
    .querySelectorAll(includedSelectors)
    .forEach((element) => {
      const cleanedElement =
        getCleanedElement(element);
      text += `${getCleanedText(cleanedElement)} `;
    });
  return text.replace(/\n\s*\n/g, "\n").trim();
})();
JS;
    $translatableText = $this->getSession()->evaluateScript("return $js");
    return is_string($translatableText)
      ? $translatableText
      : '';
  }

}
