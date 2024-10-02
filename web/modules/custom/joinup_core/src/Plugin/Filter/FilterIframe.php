<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\filter\Attribute\Filter;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Drupal\filter\Plugin\FilterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to filter iframe tags.
 */
#[Filter(
  id: "joinup_iframe",
  title: new TranslatableMarkup("Joinup iframe filter"),
  type: FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
  description: new TranslatableMarkup("Filters <code>iframe</code> tags according to allowed domains."),
  settings: [
    "allowed_domains" => "",
  ]
)]
class FilterIframe extends FilterBase implements ContainerFactoryPluginInterface {

  const WEBTOOLS_COOKIE_CONSENT_EMBED_COOKIE_URL = '//webtools.europa.eu/crs/iframe/';

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected RequestContext $requestContext) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('router.request_context'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getConfiguration()['settings'];
    $form['allowed_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed domains'),
      '#description' => $this->t('List of domains to allow for iframe src attribute, 1 per line (* wildcard available). <br />The local domain is allowed by default.'),
      '#default_value' => $settings['allowed_domains'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $response = new FilterProcessResult();

    // Ensure that we have iframes in the markup.
    if (stripos($text, '<iframe') === FALSE) {
      return $response->setProcessedText($text);
    }

    $allowed_domains = $this->getAllowedDomains($this->settings['allowed_domains']);
    $dom = Html::load($text);

    // Iterate in reverse since we are removing items.
    $iframes = \iterator_to_array($dom->getElementsByTagName('iframe'));
    foreach (\array_reverse($iframes) as $iframe) {
      \assert($iframe instanceof \DOMElement);
      $src = $iframe->getAttribute('src');

      // Remove iframes with empty src.
      if (empty($src)) {
        $iframe->parentNode->removeChild($iframe);
        continue;
      }

      // Skip iframes with local URLs.
      if (!UrlHelper::isExternal($src) || UrlHelper::externalIsLocal($src, $this->requestContext->getCompleteBaseUrl())) {
        continue;
      }

      // Remove iframes with non-allowed domains.
      if (!$this->isAllowedDomain($src, $allowed_domains)) {
        $iframe->parentNode->removeChild($iframe);
        continue;
      }

      // Iframe allowed, add webtools shield.
      $shielded_src = Url::fromUri(static::WEBTOOLS_COOKIE_CONSENT_EMBED_COOKIE_URL, [
        'query' => [
          'oriurl' => $src,
          'lang' => $langcode,
        ],
      ]);
      $iframe->setAttribute('src', $shielded_src->toString());

      // Add 16/9 ratio wrapper if width/height are not specified.
      if (!$iframe->hasAttribute('width') && !$iframe->hasAttribute('height')) {
        $wrapper = $dom->createElement('div');
        $wrapper->setAttribute('class', 'ratio ratio-16x9');
        $iframe->parentNode->replaceChild($wrapper, $iframe);
        $wrapper->appendChild($iframe);
      }
    }

    $response->setProcessedText(Html::serialize($dom));
    return $response;
  }

  /**
   * Returns whether an iframe src domain is allowed.
   *
   * @param string $src
   *   The iframe src attribute.
   * @param array $allowed_domains
   *   Array of allowed domain regexes.
   *
   * @return bool
   *   TRUE if this is local URL, false otherwise.
   */
  protected function isAllowedDomain(string $src, array $allowed_domains): bool {
    $host = parse_url($src, PHP_URL_HOST);
    if (!empty($host)) {
      foreach ($allowed_domains as $regex) {
        if (preg_match($regex, $host)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Returns allowed domain regexes.
   *
   * @param string $allowed
   *   String of domains (with wildcard) to work with.
   *
   * @return array
   *   Array of regexes to match domains (with wildcard).
   */
  protected function getAllowedDomains(string $allowed): array {
    $regexes = explode("\n", $allowed);
    return array_map(static function ($host) {
      $host = trim($host);
      $host = preg_quote($host, NULL);
      $host = preg_replace('/\\\\\*/', '.*?', $host);
      return '/^' . $host . '$/';
    }, $regexes);
  }

}
