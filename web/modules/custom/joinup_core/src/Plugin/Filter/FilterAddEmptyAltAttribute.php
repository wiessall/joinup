<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

/**
 * Provides a filter which adds an empty alt attribute to all images.
 *
 * @Filter(
 *   id = "filter_add_empty_alt_attribute",
 *   title = @Translation("Add empty alt attribute"),
 *   description = @Translation("It adds empty alt attribute to all images."),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_REVERSIBLE,
 *   weight = 9,
 * )
 */
class FilterAddEmptyAltAttribute extends FilterBase {

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    // Don't process the filter if no image elements are found.
    if (stristr($text, '<img') === FALSE) {
      return new FilterProcessResult($text);
    }

    $dom = Html::load($text);
    $xpath = new \DOMXPath($dom);

    // Process each image element found without alt attribute.
    /** @var \DOMElement $dom_element */
    foreach ($xpath->query('//img[not(@alt)]') as $dom_element) {
      $dom_element->setAttribute('alt', '');
    }

    return new FilterProcessResult(Html::serialize($dom));
  }

}
