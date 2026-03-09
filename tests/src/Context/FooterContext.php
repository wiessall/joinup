<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\NodeElement;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AssertTrait;

/**
 * Behat step definitions to interact with the page footer.
 */
class FooterContext extends RawDrupalContext {

  use AssertTrait;

  /**
   * Checks that the Interoperable Europe logo is present in the footer.
   */
  #[Then('I should see the Interoperable Europe logo in the footer')]
  public function assertLogo(): void {
    $logo_element = $this->getSession()->getPage()->find('css', 'footer .joinup-logo');
    $this->assertCondition($logo_element instanceof NodeElement, 'A logo is present in the navbar.');
    $link_element = $this->getSession()->getPage()->find('css', 'footer .joinup-logo a');
    $this->assertCondition($link_element instanceof NodeElement, 'A link for the logo is present in the footer.');
    $this->assertSameValues(base_path(), $link_element->getAttribute('href'), 'The logo links to the homepage.');
  }

  /**
   * Checks that the version is present in the footer.
   */
  #[Then('I should see the Interoperable Europe Portal version in the footer')]
  public function assertVersion(): void {
    $element = $this->getSession()->getPage()->find('css', 'footer .joinup-version a');
    $this->assertCondition($element instanceof NodeElement, 'The version is shown in the footer.');
    $this->assertCondition(str_starts_with($element->getAttribute('href') ?? '', 'https://git.fpfis.tech.ec.europa.eu/ec-europa/digit-joinup-reference/'), 'The version links to the Gitlab instance.');
    $this->assertCondition((bool) preg_match('/^v?(\d+\.){2}\d+(-\d+-g[0-9a-f]+)?$/', $element->getText()), 'The version text does not match the expected format.');
  }

}
