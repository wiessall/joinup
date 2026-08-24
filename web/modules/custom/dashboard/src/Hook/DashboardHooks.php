<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Hook implementations for dashboard.
 */
final class DashboardHooks {

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id,): void {
    $ids = [
      'entity_subqueue_spotlight_edit_form',
    ];
    if (!in_array($form_id, $ids)) {
      return;
    }
    $form['actions']['submit']['#submit'][] = static::class . ':dashboardRedirectCuratedContentListing';
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
    $url = Url::fromRoute('view.curated_content_listings.page');
    $form_state->setRedirectUrl($url);
  }

}
