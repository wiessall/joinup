<?php

declare(strict_types = 1);

namespace Drupal\ventuno_helper;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Decodes a text with htmlspecialchars_decode specific Twig filters.
 */
class HtmlSpecialCharsDecodeFilter extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [
      new TwigFilter('htmlspecialchars_decode', [$this, 'filter']),
    ];
  }

  /**
   * Decodes a text with htmlspecialchars_decode.
   *
   * @param string $text
   *   The text.
   *
   * @return string
   *   The encoded string.
   */
  public function filter(string $text): string {
    return htmlspecialchars_decode($text);
  }

}
