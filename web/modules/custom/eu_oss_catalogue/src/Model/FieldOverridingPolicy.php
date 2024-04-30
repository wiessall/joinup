<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model;

/**
 * Field resolvers override policy.
 */
enum FieldOverridingPolicy: string {

  // Override any existing value in the field (default).
  case ALWAYS = 'always';

  // Preserve any non-empty value.
  case PRESERVE_NOT_EMPTY = 'preserve_not_empty';

  // Override only if resolved value is not empty.
  case RESOLVED_NOT_EMPTY = 'resolved_not_empty';

}
