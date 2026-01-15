<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Element\NodeElement;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions to interact with the page header.
 */
class HeaderContext extends RawDrupalContext {

  /**
   * Maps HTML element names to human readable aliases.
   */
  protected const HTML_ELEMENT_ALIASES = [
    'link' => 'a',
    'button' => 'button',
  ];

  /**
   * Maps Bootstrap icon identifiers to human readable aliases.
   */
  protected const ICON_ALIASES = [
    'hamburger' => 'justify',
    'profile' => 'person-circle',
  ];

  /**
   * Checks that the Interoperable Europe logo is present in the navigation bar.
   */
  #[Then('I should see the Interoperable Europe logo in the navigation bar')]
  public function assertLogo(): void {
    $link_element = $this->getSession()->getPage()->find('css', '.joinup-navbar a.navbar-brand');
    Assert::assertInstanceOf(NodeElement::class, $link_element, 'A link for the logo is present in the navbar.');
    Assert::assertEquals(base_path(), $link_element->getAttribute('href'), 'The logo links to the homepage.');
    $logo_element = $link_element->find('css', 'img.navbar-brand-image');
    Assert::assertInstanceOf(NodeElement::class, $logo_element, 'A logo is present in the navbar.');
    Assert::assertEquals('Interoperable Europe logo', $logo_element->getAttribute('alt'), 'The logo has an alt text.');
    $expected_logo_uri = base_path() . \Drupal::service('extension.list.theme')->getPath('ventuno') . '/assets/images/logo-interoperable-europe.svg';
    Assert::assertEquals($expected_logo_uri, $logo_element->getAttribute('src'), 'The Interoperable Europe Portal logo is shown in the navbar.');
  }

  /**
   * Checks the content of the main navigation on desktop and mobile.
   *
   * @param \Behat\Gherkin\Node\TableNode $items
   *   The expected items that are present in the main navigation bar.
   */
  #[Then('I should see the following items in the main navigation:')]
  public function assertMainNavigationItems(TableNode $items): void {
    // Retrieve the main navigation links on desktop.
    $desktop_items = $this->getSession()->getPage()->findAll('xpath', '//header[contains(concat(" ", normalize-space(@class), " "), " joinup-navbar ")]//ul[contains(concat(" ", normalize-space(@class), " "), " navbar-nav ")]/li/*[self::a or self::button]');
    // Retrieve the main navigation links on mobile.
    $mobile_items = $this->getSession()->getPage()->findAll('css', '.joinup-navbar ul.navbar-nav .mmenu-nav__menu a');
    foreach ($items->getColumnsHash() as $item) {
      // Check if the item is present in the desktop menu.
      if ($item['desktop menu'] !== 'not shown') {
        $actual_item = array_shift($desktop_items);

        // Check that the item exists.
        Assert::assertInstanceOf(NodeElement::class, $actual_item, sprintf('Expected the %s item in the main navigation menu but instead found nothing.', $item['link']));

        // Check that the item is the expected type.
        $actual_tag = $actual_item->getTagName();
        Assert::assertEquals(self::HTML_ELEMENT_ALIASES[$item['desktop menu']], $actual_tag, sprintf('Expected that the %s item in the main navigation menu was a %s but instead found a %s element.', $item['link'], $item['desktop menu'], $actual_tag));

        // Check the item text.
        $actual_text = trim($actual_item->getHtml());
        Assert::assertEquals($item['link'], $actual_text, sprintf('Expected the %s item in the main navigation menu but instead found %s.', $item['link'], $actual_text));
      }

      // Check the mobile menu.
      if ($item['mobile menu'] !== 'not shown') {
        $actual_item = array_shift($mobile_items);

        // Check that the item exists.
        Assert::assertInstanceOf(NodeElement::class, $actual_item, sprintf('Expected the %s item in the mobile menu but instead found nothing.', $item['link']));

        // Check that the item is the expected type.
        $actual_tag = $actual_item->getTagName();
        Assert::assertEquals(self::HTML_ELEMENT_ALIASES[$item['mobile menu']], $actual_tag, sprintf('Expected that the %s item in the mobile menu was a %s but instead found a %s element.', $item['link'], $item['mobile menu'], $actual_tag));

        // Check the item text.
        $actual_text = trim($actual_item->getHtml());
        Assert::assertEquals($item['link'], $actual_text, sprintf('Expected the %s item in the mobile menu but instead found %s.', $item['link'], $actual_text));
      }
    }

    Assert::assertNotEmpty($desktop_items);
    Assert::assertEmpty($mobile_items);
  }

  /**
   * Checks that the requested menu icon is present in the header.
   *
   * @param string $icon
   *   The icon that should be visible. Can be either 'profile' or 'hamburger'.
   */
  #[Then('I should see the :icon icon in the main navigation')]
  public function assertHamburgerMenuIconPresent(string $icon): void {
    assert(array_key_exists($icon, self::ICON_ALIASES), 'Unknown icon ' . $icon);

    $icon_uri = base_path() . \Drupal::service('extension.list.theme')->getPath('ventuno') . '/assets/icons/icons.svg#' . self::ICON_ALIASES[$icon];
    $icon_selector = '.joinup-navbar .nav-item svg > use';
    foreach ($this->getSession()->getPage()->findAll('css', $icon_selector) as $icon) {
      if ($icon->getAttribute('xlink:href') === $icon_uri) {
        // We found the icon.
        return;
      }
    }

    throw new \RuntimeException("The $icon icon was expected to be present in the main navigation on mobile, but it was not.");
  }

}
