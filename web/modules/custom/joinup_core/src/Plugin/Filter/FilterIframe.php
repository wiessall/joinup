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
    "allowed_domains" => [],
  ]
)]
class FilterIframe extends FilterBase implements ContainerFactoryPluginInterface {

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
      '#default_value' => implode("\r\n", $settings['allowed_domains']),
      '#element_validate' => [[static::class, 'validateAllowedDomains']],
    ];
    return $form;
  }

  /**
   * Validates allowed domains (convert to array for config storage).
   */
  public static function validateAllowedDomains(array &$element, FormStateInterface $form_state): void {
    $value = array_filter(array_map('trim', explode("\n", $element['#value'])));
    $form_state->setValueForElement($element, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode): FilterProcessResult {
    $response = new FilterProcessResult();

    // Ensure that we have iframes in the markup.
    if (str_contains($text, '<iframe') === FALSE) {
      return $response->setProcessedText($text);
    }

    $allowed_domains = $this->getAllowedDomainRegexes($this->settings['allowed_domains']);
    $dom = Html::load($text);

    // Iterate in reverse since we are removing items.
    $iframes = \iterator_to_array($dom->getElementsByTagName('iframe'));
    foreach (\array_reverse($iframes) as $iframe) {
      \assert($iframe instanceof \DOMElement);
      $src = $iframe->getAttribute('src');

      // Remove if src is empty.
      if (empty($src)) {
        $iframe->parentNode->removeChild($iframe);
        continue;
      }

      if ($this->isExternal($src)) {
        // Remove if src is external but non-allowed domain.
        if (!$this->isAllowedDomain($src, $allowed_domains)) {
          $iframe->parentNode->removeChild($iframe);
          continue;
        }

        // External iframe allowed, add webtools shield.
        $iframe->setAttribute('src', Url::fromUri(\OE_WEBTOOLS_COOKIE_CONSENT_EMBED_COOKIE_URL, [
          'query' => [
            'oriurl' => $src,
            'lang' => $langcode,
          ],
        ])->toString());
      }

      // Add 16/9 ratio wrapper div.
      $wrapper = $dom->createElement('div');
      $wrapper->setAttribute('class', 'ratio ratio-16x9');
      $iframe->parentNode->replaceChild($wrapper, $iframe);
      $wrapper->appendChild($iframe);
    }

    return $response->setProcessedText(Html::serialize($dom));
  }

  /**
   * Returns whether an iframe src is external.
   */
  protected function isExternal(string $src): bool {
    return UrlHelper::isExternal($src) && !UrlHelper::externalIsLocal($src, $this->requestContext->getCompleteBaseUrl());
  }

  /**
   * Returns whether an iframe src is allowed according to the domain/host.
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
   * @param array $allowed
   *   Array of domains strings.
   *
   * @return array
   *   Array of regexes to match domains (with wildcard).
   */
  protected function getAllowedDomainRegexes(array $allowed): array {
    return array_map(static function ($host) {
      $host = preg_replace('/\\\\\*/', '.*?', preg_quote($host, NULL));
      return '/^' . $host . '$/';
    }, $allowed);
  }

}
