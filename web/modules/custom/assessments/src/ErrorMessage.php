<?php

declare(strict_types=1);

namespace Drupal\assessments;

/**
 * List of error strings we use to alter.
 *
 * @see assessments_joinup_api_openapi_error_alter()
 */
enum ErrorMessage: string {

  case OneOf = 'Failed to match exactly one schema';
  case ObjectFoundStringRequired = 'Object value found, but a string is required';
  case ObjectFoundStringRequiredAlt = 'An object or a string is required. Object value found, but is invalid';
  case StringFoundObjectRequired = 'String value found, but an object is required';
  case StringFoundObjectRequiredAlt = 'An object or a string is required. String value found, but is invalid';

}
