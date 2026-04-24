<?php

declare(strict_types=1);

namespace Drupal\assessments\Render;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Pre-render callback to disable scrolling on entity browser iframes.
 */
class AssessmentIframePreRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['disableScrolling'];
  }

  /**
   * Adds scrolling="no" to entity browser modal iframes.
   *
   * @param array $element
   *   The html_tag render element.
   *
   * @return array
   *   The modified render element.
   */
  public static function disableScrolling(array $element): array {
    if (($element['#tag'] ?? NULL) !== 'iframe') {
      return $element;
    }

    $classes = (array) ($element['#attributes']['class'] ?? []);
    if (!in_array('entity-browser-modal-iframe', $classes)) {
      return $element;
    }

    $element['#attributes']['scrolling'] = 'no';

    return $element;
  }

}
