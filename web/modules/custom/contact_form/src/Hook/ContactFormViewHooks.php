<?php

declare(strict_types=1);

namespace Drupal\contact_form\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Hook implementations for contact form views.
 */
final class ContactFormViewHooks {

  public function __construct(
    protected readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_preprocess_HOOK() for html.html.twig.
   */
  #[Hook('preprocess_html')]
  public function preprocessHtml(array &$variables): void {
    if ($this->routeMatch->getRouteName() == 'contact_form.contact_page') {
      $metadesc = [
        '#tag' => 'meta',
        '#attributes' => [
          'name' => 'description',
          'content' => 'Use this form to submit your requests, queries or issues (to be processed by a contractor of the European Commission).',
        ],
      ];
      $variables['page']['#attached']['html_head'][] = [$metadesc, 'description'];
    }
  }

}
