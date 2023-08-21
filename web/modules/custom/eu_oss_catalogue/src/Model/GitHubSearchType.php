<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Model;

/**
 * GitHub search types.
 *
 * @see https://docs.github.com/en/graphql/reference/enums#searchtype
 */
enum GitHubSearchType {

  case DISCUSSION;
  case ISSUE;
  case REPOSITORY;
  case USER;

}
