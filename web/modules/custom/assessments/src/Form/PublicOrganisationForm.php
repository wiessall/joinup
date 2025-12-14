<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\assessments\Entity\PublicOrganisation;

/**
 * Form controller for the assessment entity edit forms.
 */
final class PublicOrganisationForm {

  /**
   * Ajax callback to refresh dynamic fields based on organisation type.
   */
  public static function ajaxRefresh(array &$form, FormStateInterface $form_state): array {
    $form_state->setRebuild(TRUE);
    return $form['public_org_dynamic_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public static function formAlter(array &$form, FormStateInterface $form_state): void {
    // Wrap dynamic section for AJAX refresh.
    $form['public_org_dynamic_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'public-org-dynamic-wrapper'],
    ];

    // Convert state-driven logic to AJAX: organisation_type triggers refresh.
    $form['organisation_type']['widget'][0]['value']['#ajax'] = [
      'callback' => [self::class, 'ajaxRefresh'],
      'event' => 'change',
      'wrapper' => 'public-org-dynamic-wrapper',
    ];

    // Determine current org type value from form state or defaults.
    $org_type = NULL;
    if ($form_state->hasValue(['fields', 'organisation_type', 0, 'value'])) {
      $org_type = $form_state->getValue(['fields', 'organisation_type', 0, 'value']);
    }
    elseif ($form_state->hasValue(['organisation_type', 0, 'value'])) {
      $org_type = $form_state->getValue(['organisation_type', 0, 'value']);
    }
    elseif (isset($form['organisation_type']['widget'][0]['value']['#default_value'])) {
      $org_type = $form['organisation_type']['widget'][0]['value']['#default_value'];
    }

    // Show/hide and required rules via server-side render instead of #states.
    $show_basic = in_array($org_type, [
      'local_public_sector_body',
      'regional_public_sector_body',
      'national_public_sector_body',
    ], TRUE);

    if (isset($form['country'])) {
      // Move country into the dynamic wrapper and control visibility.
      $form['country']['#access'] = $show_basic;
      $form['public_org_dynamic_wrapper']['country'] = $form['country'];
      unset($form['country']);
    }
    if (isset($form['name'])) {
      // Move name into the dynamic wrapper, and make it required conditionally.
      $form['name']['#access'] = $show_basic;
      $form['name']['#required'] = $show_basic;
      $form['public_org_dynamic_wrapper']['name'] = $form['name'];
      unset($form['name']);
    }

    // Place the EU selects inside the wrapper and control visibility.
    $form['public_org_dynamic_wrapper']['eu_agency_body_institution'] = [
      '#type' => 'select',
      '#title' => t('European Union institution'),
      '#options' => PublicOrganisation::getEuropeanInstitutionOptions(),
      '#empty_option' => t('- Select -'),
      '#access' => $org_type === 'european_union_institution',
      '#required' => $org_type === 'european_union_institution',
    ];

    $form['public_org_dynamic_wrapper']['eu_agency_body_institution_body'] = [
      '#type' => 'select',
      '#title' => t('European Union body'),
      '#options' => PublicOrganisation::getEuropeanBodyOptions(),
      '#empty_option' => t('- Select -'),
      '#access' => $org_type === 'european_union_body',
      '#required' => $org_type === 'european_union_body',
    ];

    $form['public_org_dynamic_wrapper']['eu_agency_body_institution_agency'] = [
      '#type' => 'select',
      '#title' => t('European Union agency'),
      '#options' => PublicOrganisation::getEuropeanAgencyOptions(),
      '#empty_option' => t('- Select -'),
      '#access' => $org_type === 'european_union_agency',
      '#required' => $org_type === 'european_union_agency',
    ];

  }

}
