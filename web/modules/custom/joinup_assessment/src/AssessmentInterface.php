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
   * Assessments report title pattern.
   */
  public const string REPORT_TITLE_PATTERN = 'Assessment report #%s';

  /**
   * Question used to generate abstract value.
   */
  public const string SUBMISSION_ABSTRACT = 'initiative';

  /**
   * Webform which contains assessment questions.
   */
  public const string WEBFORM_ID = 'assessment';

}
