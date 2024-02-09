<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Model\GitHub;

/**
 * Issue state.
 *
 * @see https://docs.github.com/en/graphql/reference/enums#issuestate
 */
enum IssueState {

  case OPEN;
  case CLOSED;

}
