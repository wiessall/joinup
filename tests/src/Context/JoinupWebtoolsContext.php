<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\BrowserCapabilityDetectionTrait;

/**
 * Behat step definitions for testing events.
 */
class JoinupWebtoolsContext extends RawDrupalContext {

  use BrowserCapabilityDetectionTrait;

  /**
   * Lets the user accept essential or all cookies in the cookie consent banner.
   *
   * @param string $scope
   *   The scope of the cookies to accept. Can be either 'all' or 'essential'.
   *
   * @Given I accept :scope cookies
   */
  public function acceptCookies(string $scope): void {
    // The cookie banner is only shown in JS supporting browsers.
    $this->assertJavaScriptEnabledBrowser();

    // Wait for the cookie consent banner to appear.
    $result = $this->getSession()->getPage()->waitFor(5, function () {
      $element = $this->getSession()->getPage()->find('css', '#cookie-consent-banner');
      return $element && $element->isVisible();
    });

    if (!$result) {
      throw new \RuntimeException("The cookie consent banner did not appear.");
    }

    $link_text = $scope === 'all' ? 'Accept all cookies' : 'Accept only essential cookies';
    $this->getSession()->getPage()->clickLink($link_text);

    // Wait for the close button to appear.
    $close_link_selector = '#cookie-consent-banner .cck-actions .wt-link[href="#close"]';
    $result = $this->getSession()->getPage()->waitFor(5, function () use ($close_link_selector) {
      $element = $this->getSession()->getPage()->find('css', $close_link_selector);
      return $element && $element->isVisible();
    });

    if (!$result) {
      throw new \RuntimeException("The cookie consent banner could not be closed because the close link doesn't appear.");
    }

    $this->getSession()->getPage()->find('css', $close_link_selector)->click();
  }

}
