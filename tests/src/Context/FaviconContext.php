<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Mink\Element\NodeElement;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions to detect the presence of favicons.
 */
class FaviconContext extends RawDrupalContext {

  /**
   * Lists acceptable MIME types for favicons.
   */
  protected const MIME_TYPES = [
    'image/vnd.microsoft.icon',
    'image/png',
  ];

  /**
   * Detects if a favicon is shown in the page.
   *
   * This will iterate over all favicons that are present in the page, and check
   * that all of them exist as image files.
   *
   * We are assuming that at least one of them will match the user's device.
   */
  #[Then('I should see a favicon')]
  public function assertFavicons(): void {
    $uris = $this->getFaviconUris();

    if (empty($uris)) {
      throw new \RuntimeException('No favicons are present in the page.');
    }

    $base_url = $this->getMinkParameter('base_url');
    $host = parse_url($base_url, PHP_URL_HOST);
    $scheme = parse_url($base_url, PHP_URL_SCHEME);
    if ($port = parse_url($base_url, PHP_URL_PORT)) {
      $host = "$host:$port";
    }

    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    foreach ($uris as $uri) {
      $url = $scheme . '://' . $host . $uri;
      try {
        $file = file_get_contents($url);
        if ($file === FALSE) {
          throw new \Exception();
        }
      }
      catch (\Exception $e) {
        throw new \RuntimeException(sprintf('Cannot open favicon URL: %s', $url));
      }
      $mime_type = $finfo->buffer($file);
      Assert::assertTrue(in_array($mime_type, self::MIME_TYPES), sprintf('The favicon with URL "%s" has a MIME type of "%s" which is not in the list of acceptable MIME types.', $url, $mime_type));
    }
  }

  /**
   * Returns the available favicons as URIs.
   *
   * @return string[]
   *   The favicons as absolute URIs.
   */
  protected function getFaviconUris(): array {
    $xpath = '//link[@rel="icon" or @rel="apple-touch-icon"]';
    $elements = $this->getSession()->getPage()->findAll('xpath', $xpath);
    return array_filter(array_map(function (NodeElement $element): string {
      return $element->getAttribute('href');
    }, $elements));
  }

}
