<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\iframe\Plugin\Field\FieldFormatter\IframeOnlyFormatter;

/**
 * Plugin implementation of the 'iframe_with_consent_declaration' formatter.
 */
#[FieldFormatter(
  id: 'iframe_with_consent_declaration',
  label: new TranslatableMarkup('Iframe with consent declaration'),
  field_types: [
    'iframe',
  ],
)]
class IframeWithConsentDeclarationFormatter extends IframeOnlyFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as &$element) {
      if (self::isSupported($element['#src']) && $element['#attributes'] instanceof Attribute) {
        $element['#attributes']->setAttribute(
          'src',
          self::replaceUrlToConsentDeclaration($element['#src'], $langcode)
        );
      }
    }

    return $elements;
  }

  /**
   * Replaces Url to consent declaration.
   *
   * @param string $src
   *   Iframe URL.
   * @param string $lang
   *   The language that should be used to render the field.
   *
   * @return string
   *   Consent declaration URL.
   */
  protected function replaceUrlToConsentDeclaration(string $src, string $lang): string {
    return Url::fromUri(\OE_WEBTOOLS_COOKIE_CONSENT_EMBED_COOKIE_URL, [
      'query' => [
        'oriurl' => $src,
        'lang' => $lang,
      ],
    ])->toString();
  }

  /**
   * Checks if the consent declaration is supported.
   *
   * @param string $src
   *   Iframe URL.
   *
   * @return bool
   *   TRUE if consent declaration is supported
   */
  protected function isSupported(string $src): bool {
    $supported = [
      'app.powerbi.com',
    ];

    $parsed = parse_url($src);
    return in_array($parsed['host'], $supported, TRUE);
  }

}
