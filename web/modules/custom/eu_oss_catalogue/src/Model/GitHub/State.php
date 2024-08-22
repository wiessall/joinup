<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model\GitHub;

/**
 * GitHub status filter values.
 *
 * @see https://docs.github.com/en/graphql/reference/enums#discussionstate
 * @see https://docs.github.com/en/graphql/reference/enums#issuestate
 * @see https://docs.github.com/en/graphql/reference/enums#milestonestate
 * @see https://docs.github.com/en/graphql/reference/enums#projectstate
 * @see https://docs.github.com/en/graphql/reference/enums#projectv2state
 * @see https://docs.github.com/en/graphql/reference/enums#pullrequeststate
 * @see https://docs.github.com/en/graphql/reference/enums#pullrequestupdatestate
 * @see https://docs.github.com/en/graphql/reference/enums#trackedissuestates
 */
enum State {

  case OPEN;
  case CLOSED;
  case MERGED;

}
