<?php

declare(strict_types=1);

namespace Drupal\assessments\Render;

use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Render callbacks altering the Layout Paragraphs builder element.
 */
class LayoutParagraphsBuilderAlter implements TrustedCallbackInterface {

  /**
   * Paragraph bundle that is a layout section but takes no nested components.
   */
  protected const RESTRICTED_BUNDLE = 'binding_requirement';

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['removeRegionInsertButtons'];
  }

  /**
   * Drops the "add component" button from binding requirement regions.
   *
   * A binding requirement is a one-column layout section, so Layout Paragraphs
   * offers an "add component" button inside its region. Nesting components in a
   * binding requirement is not supported, so that button has to go.
   *
   * Hiding it with a 'visually hidden' class does not work: the builder script
   * adds and removes exactly that class on every '.js-lpb-ui' element whenever
   * a component is selected, so the button reappears on the first click.
   *
   * @see \Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder::preRender()
   * @see \Drupal\layout_paragraphs\Element\LayoutParagraphsBuilder::addJsUiElement()
   */
  public static function removeRegionInsertButtons(array $element): array {
    // A full build holds a flat list of every component in the layout.
    if (isset($element['#components'])) {
      foreach ($element['#components'] as &$component) {
        static::stripRegionInsertButtons($component);
      }
      unset($component);
    }
    // An Ajax response rebuilding a single component is reduced to its build.
    elseif (isset($element['build'])) {
      static::stripRegionInsertButtons($element['build']);
    }

    return $element;
  }

  /**
   * Removes the region insert buttons from a single component build.
   *
   * The buttons are not part of the render tree. They are rendered to markup
   * and passed to the builder script through drupalSettings, keyed by the
   * 'data-lpb-ui-id' attribute of the element they get attached to.
   */
  protected static function stripRegionInsertButtons(array &$component): void {
    if (($component['#attributes']['data-type'] ?? NULL) !== static::RESTRICTED_BUNDLE) {
      return;
    }
    if (!isset($component['regions'])) {
      return;
    }

    foreach (Element::children($component['regions']) as $regionName) {
      $region = &$component['regions'][$regionName];
      $uiId = $region['#attributes']['data-lpb-ui-id'] ?? NULL;
      if ($uiId === NULL) {
        continue;
      }

      $uiElements = &$region['#attached']['drupalSettings']['lpBuilder']['uiElements'];
      unset($uiElements[$uiId]['insert']);
      // Leave no empty leftovers behind in drupalSettings.
      if (empty($uiElements[$uiId])) {
        unset($uiElements[$uiId]);
      }
      unset($uiElements);
    }
    unset($region);
  }

}
