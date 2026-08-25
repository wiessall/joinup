<?php

declare(strict_types=1);

namespace Drupal\dashboard\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Url;

/**
 * Hook implementations for dashboard forms.
 */
final class DashboardFormHooks {

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   *  Change the EntitySubqueueForm::save() because when they
   *  save a content listing they go to access denied page
   *  instead of Curated content listings page.
   */
  #[Hook('form_entity_subqueue_spotlight_edit_form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState, string $formId,): void {
    $form['actions']['submit']['#submit'][] = static::class . ':dashboardRedirectCuratedContentListing';
  }

  /**
   * Redirects users to curated content listings after saving a content list.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function dashboardRedirectCuratedContentListing(array &$form, FormStateInterface $formState,): void {
    $url = Url::fromRoute('view.curated_content_listings.page');
    $formState->setRedirectUrl($url);
  }

}
