<?php

namespace Drupal\eu_oss_catalogue_platform_github\GraphQL\DataModel;

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
