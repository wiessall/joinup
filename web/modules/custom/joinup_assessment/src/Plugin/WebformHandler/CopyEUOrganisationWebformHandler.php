<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

/**
 * Copy EU into organization webform Handler.
 *
 * @WebformHandler(
 *   id = "joinup_assessment_copy_organisation",
 *   label = @Translation("Copy EU into organization"),
 *   category = @Translation("Portal"),
 *   description = @Translation("Copies the EU institution/agency/body into organization."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   submission = {
 *     "add",
 *     "update"
 *   }
 * )
 */
class CopyEUOrganisationWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission): void {
    // Retrieve submission values.
    $values = $webform_submission->getData();

    // Check the 'public_sector_body' field value.
    $public_sector_body = $values['public_sector_body'] ?? '';
    $valid_bodies = [
      'European Union institution',
      'European Union agency',
      'European Union body',
    ];

    if (in_array($public_sector_body, $valid_bodies, TRUE)) {
      // Combine relevant fields into 'organisation'.
      $organisation = sprintf(
        '%s %s %s',
        $values['institution'] ?? '',
        $values['body'] ?? '',
        $values['agency'] ?? ''
      );

      // Set the 'organisation' field value.
      $webform_submission->setData(['organisation' => $organisation] + $values);
    }
  }

}
