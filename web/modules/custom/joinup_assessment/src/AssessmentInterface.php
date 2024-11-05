<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment;

/**
 * Provides an interface for assessment constants.
 */
interface AssessmentInterface {

  /**
   * Group URI.
   *
   * Space where document's assessments are stored.
   */
  public const string DOCUMENT_GROUP_URI = 'http://data.europa.eu/w21/c5e99eae-6d31-47a0-b914-a4b9f4ea82cf';

  /**
   * State.
   *
   * Default value for a new document.
   */
  public const string DOCUMENT_STATE = 'proposed';

  /**
   * Type of document.
   *
   * Default value for a new document.
   */
  public const string DOCUMENT_TYPE_KEY = 'interoperability_assessment';

  /**
   * Topic.
   *
   * Default value for a new document.
   */
  public const string DOCUMENT_TOPIC = 'European Policies';

  /**
   * Question used to generate abstract value.
   */
  public const string SUBMISSION_ABSTRACT = 'please_provide_the_name_of_the_initiative_project_or_action_cont';

  /**
   * Webform ID.
   *
   * Webform which contains assessment questions.
   */
  public const string WEBFORM_ID = 'online_form_for_assessments';

}
