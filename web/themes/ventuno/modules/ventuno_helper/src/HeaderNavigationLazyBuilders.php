<?php

declare(strict_types=1);

namespace Drupal\ventuno_helper;

use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Lazy builders for the destination-aware anonymous header navigation links.
 */
class HeaderNavigationLazyBuilders {

  use StringTranslationTrait;

  public function __construct(
    protected readonly RedirectDestinationInterface $redirectDestination,
  ) {}

  /**
   * Lazy builder callback; renders a "Sign in" link to the CAS login.
   *
   * @param string $classes
   *   A space separated list of classes for the anchor, or an empty string.
   *
   * @return array
   *   The render array for the sign in link.
   */
  #[TrustedCallback]
  public function renderSignInLink(string $classes): array {
    $element = [
      '#type' => 'link',
      '#title' => $this->t('Sign in'),
      '#url' => $this->casLoginUrl(),
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
    ];
    if ($classes !== '') {
      $element['#attributes']['class'] = explode(' ', $classes);
    }
    return $element;
  }

  /**
   * Lazy builder callback; renders the "Get started" call to action.
   *
   * @return array
   *   The render array for the "Create an account" call to action anchor.
   */
  #[TrustedCallback]
  public function renderGetStartedCta(): array {
    return [
      '#theme' => 'joinup_header_get_started_cta',
      '#url' => $this->casLoginUrl(),
      '#cache' => ['contexts' => ['url.path', 'url.query_args']],
    ];
  }

  /**
   * Builds the CAS login URL carrying the current redirect destination.
   *
   * @return \Drupal\Core\Url
   *   The CAS login URL.
   */
  protected function casLoginUrl(): Url {
    return Url::fromRoute('cas.login', [], [
      'query' => $this->redirectDestination->getAsArray(),
    ]);
  }

}
