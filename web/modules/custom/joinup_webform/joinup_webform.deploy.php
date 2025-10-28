<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

use Drupal\webform\Entity\WebformSubmission;

/**
 * Migrate initiative and initiative_link into initiatives composite.
 */
function joinup_webform_deploy_200701(): void {
  $query = \Drupal::entityQuery('webform_submission')
    ->condition('webform_id', 'assessment')
    ->accessCheck(FALSE);

  $sids = $query->execute();

  foreach (WebformSubmission::loadMultiple($sids) as $submission) {
    $data = $submission->getData();

    if (!empty($data['initiative']) || !empty($data['initiative_link'])) {
      $data['initiatives'] = [
        [
          'initiative' => $data['initiative'] ?? '',
          'initiative_link' => $data['initiative_link'] ?? '',
        ],
      ];

      $submission->setData($data);
      $submission->save();
    }
  }
  \Drupal::logger('joinup_webform')->notice('Migrated all values from initiative and initiative link to initiatives custom composite.');
}
