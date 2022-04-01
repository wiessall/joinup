<?php

declare(strict_types = 1);

namespace Drupal\ventuno_helper;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Provides a pre-render callback that attaches modal dialog functionality.
 */
class ModalDialogHelper implements TrustedCallbackInterface {

  /**
   * Attaches modal functionality to appropriate links.
   *
   * @param array $element
   *   The link element.
   *
   * @return array
   *   The link element.
   */
  public static function attachModalLibrary(array $element): array {
    if (static::linkOpensModal($element['#options']['attributes'] ?? [])) {
      $element['#attached']['library'][] = 'ventuno/modals';
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['attachModalLibrary'];
  }

  /**
   * Returns whether a link with given attributes does open a modal dialog.
   *
   * @param array $attributes
   *   The link attributes array.
   *
   * @return bool
   *   Whether a link with the given attributes does open a modal dialog.
   */
  public static function linkOpensModal(array $attributes): bool {
    return isset($attributes['data-dialog-type']) && $attributes['data-dialog-type'] === 'modal';
  }

}
