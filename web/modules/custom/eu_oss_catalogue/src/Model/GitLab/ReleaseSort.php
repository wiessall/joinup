<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model\GitLab;

/**
 * GitLab release sort types.
 *
 * @see https://docs.gitlab.com/ee/api/graphql/reference/#releasesort
 */
enum ReleaseSort {

  case CREATED_ASC;
  case CREATED_DESC;
  case RELEASED_AT_ASC;
  case RELEASED_AT_DESC;

}
