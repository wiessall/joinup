<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;

/**
 * Step definitions for interacting with pages using the Bootstrap framework.
 */
class BootstrapContext extends RawDrupalContext {

  use JavascriptTrait;

  /**
   * Some common screen resolutions, mapped on Bootstrap breakpoints.
   */
  protected const RESOLUTIONS = [
    // iPhone 3G.
    'extra small' => [320, 480],
    // iPhone 4.
    'small' => [640, 960],
    // Tablet.
    'medium' => [768, 1024],
    // 15" monitor.
    'large' => [1024, 768],
    // 14" laptop.
    'extra large' => [1280, 800],
    // 24" monitor.
    'extra extra large' => [1920, 1200],
  ];

  /**
   * Resizes the window to a resolution matching a Bootstrap breakpoint.
   *
   * @param string $size
   *   A human readable version of a Bootstrap breakpoint (e.g. 'extra large'
   *   instead of 'xl').
   *
   * @Given I use a device with a(n) :size display
   */
  public function changeResolution(string $size): void {
    self::assertJavaScriptEnabledBrowser();

    [$width, $height] = self::RESOLUTIONS[$size];
    $this->getSession()->resizeWindow($width, $height, 'current');
  }

}
