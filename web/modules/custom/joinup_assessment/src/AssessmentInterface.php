<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment;

/**
 * Provides an interface for assessment constants.
 */
interface AssessmentInterface {

  /**
   * UUID of the group where document's assessments are stored.
   */
  public const string GROUP_UUID = 'c5e99eae-6d31-47a0-b914-a4b9f4ea82cf';

  /**
   * Webform which contains assessment questions.
   */
  public const string WEBFORM_ID = 'assessment';

  /**
   * The assessment reports page UUID.
   */
  public const string ASSESSMENT_REPORTS_UUID = 'a9896322-f334-485c-be5d-673a106f68ad';

}
