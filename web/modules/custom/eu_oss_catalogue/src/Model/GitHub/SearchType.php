<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model\GitHub;

/**
 * GitHub search types.
 *
 * @see https://docs.github.com/en/graphql/reference/enums#searchtype
 */
enum SearchType {

  case DISCUSSION;
  case ISSUE;
  case REPOSITORY;
  case USER;

}
