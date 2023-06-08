<?php

declare(strict_types = 1);

namespace Drupal\github_feed\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemInterface;

/**
 * Interface for a Github feed field item.
 */
interface GithubFeedItemInterface extends FieldItemInterface {

  /**
   * Returns the decrypted value of the GitHub token.
   *
   * @return string
   *   The decrypted value.
   */
  public function getAccessToken(): string;

}
