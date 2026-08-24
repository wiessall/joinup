<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\UrlGeneratorInterface;

/**
 * Hook implementations for dashboard.
 */
final class DashboardHooks {

  public function __construct(
    protected readonly UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter() for the spotlight subqueue form.
   */
  #[Hook('entity_subqueue_spotlight_edit_form')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id,): void {
    $form['actions']['submit']['#submit'][] = 'dashboardRedirectCuratedContentListing';
  }

  /**
   * Redirects users to curated content listings after saving a content list.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function dashboardRedirectCuratedContentListing(array &$form, FormStateInterface $form_state,): void {
    $form_state->setRedirectUrl($this->urlGenerator->generateFromRoute('view.curated_content_listings.page'));
  }

}
