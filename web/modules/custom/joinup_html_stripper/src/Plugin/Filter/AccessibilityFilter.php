<?php

declare(strict_types=1);

namespace Drupal\joinup_html_stripper\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;

/**
 * Provides a filter to fix accessibility issues by removing empty tags.
 */
#[Filter(
  id: "joinup_accessibility_filter",
  title: new TranslatableMarkup("Accessibility filter"),
  type: FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
  description: new TranslatableMarkup("Removes empty paragraph and heading tags to improve accessibility."),
  weight: 10
)]
class AccessibilityFilter extends FilterBase {

  /**
   * Tags that render content of their own, without contributing any text.
   *
   * A paragraph holding one of these is not empty, even though `textContent`
   * and `strip_tags()` both reduce it to nothing. `drupal-media` matters most:
   * it is still a bare placeholder at this point, because `media_embed` runs
   * later in the chain and only then expands it into an actual image.
   */
  private const array EMBEDDED_CONTENT_TAGS = [
    'img',
    'drupal-media',
    'drupal-entity',
    'iframe',
    'video',
    'audio',
    'object',
    'embed',
    'picture',
    'svg',
    'canvas',
  ];

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    if (!preg_match('/<(?:p|h[1-6])[^>]*>/', $text)) {
      return new FilterProcessResult($text);
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);
    $elements_to_remove = [];

    foreach ($xpath->query('//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6') as $element) {
      if ($this->isEmpty($element, $dom)) {
        $elements_to_remove[] = $element;
      }
    }

    foreach ($elements_to_remove as $element) {
      $element->parentNode?->removeChild($element);
    }

    return new FilterProcessResult(Html::serialize($dom));
  }

  /**
   * Determines if a DOM element is empty.
   *
   * @param \DOMElement $element
   *   The DOM element to check.
   * @param \DOMDocument $dom
   *   The DOM document for context.
   *
   * @return bool
   *   TRUE if the element contains no meaningful content.
   */
  private function isEmpty(\DOMElement $element, \DOMDocument $dom): bool {
    $normalized = $this->normalizeContent($element->textContent);

    if ($normalized !== '') {
      return FALSE;
    }

    // Embedded content carries no text but is very much visible.
    foreach (self::EMBEDDED_CONTENT_TAGS as $tag) {
      if ($element->getElementsByTagName($tag)->length > 0) {
        return FALSE;
      }
    }

    $inner_html = '';
    foreach ($element->childNodes as $child) {
      $inner_html .= $dom->saveHTML($child);
    }

    // Check if the element contains anchor tags with attributes.
    // Anchor tags with attributes (like id, href) should preserve the parent
    // element.
    if (preg_match('/<a\s+[^>]*\w+[^>]*>/i', $inner_html)) {
      return FALSE;
    }

    return $this->normalizeContent(strip_tags($inner_html)) === '';
  }

  /**
   * Normalizes content by converting non-breaking spaces and whitespace.
   *
   * @param string $content
   *   The content to normalize.
   *
   * @return string
   *   The normalized content with trimmed whitespace.
   */
  private function normalizeContent(string $content): string {
    return trim(preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $content)));
  }

}
