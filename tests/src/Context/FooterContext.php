<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\NodeElement;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions to interact with the page footer.
 */
class FooterContext extends RawDrupalContext {

  /**
   * Checks that the Interoperable Europe logo is present in the footer.
   *
   * @Then I should see the Interoperable Europe logo in the footer
   */
  public function assertLogo(): void {
    $logo_element = $this->getSession()->getPage()->find('css', 'footer .joinup-logo');
    Assert::assertInstanceOf(NodeElement::class, $logo_element, 'A logo is present in the navbar.');
    $link_element = $this->getSession()->getPage()->find('css', 'footer .joinup-logo a');
    Assert::assertInstanceOf(NodeElement::class, $link_element, 'A link for the logo is present in the footer.');
    Assert::assertEquals(base_path(), $link_element->getAttribute('href'), 'The logo links to the homepage.');
  }

  /**
   * Checks that the version is present in the footer.
   *
   * @Then I should see the Portal version in the footer
   */
  public function assertVersion(): void {
    $element = $this->getSession()->getPage()->find('css', 'footer .joinup-version a');
    Assert::assertInstanceOf(NodeElement::class, $element, 'The version is shown in the footer.');
    Assert::assertStringStartsWith('https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference/', $element->getAttribute('href'), 'The version links to the Gitlab instance.');
    Assert::assertStringStartsWith('v1.', $element->getText(), 'The version number starts with the expected string "v1.".');
  }

}
