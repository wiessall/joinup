<?php

declare(strict_types=1);

namespace Drupal\joinup_tmgmt;

use Drupal\pathauto\AliasCleanerInterface;

/**
 * Cleans URL aliases segment by segment, preserving subpaths.
 */
final class PathAliasCleaner {

  public function __construct(
    private readonly AliasCleanerInterface $aliasCleaner,
  ) {}

  /**
   * Cleans an alias, normalizing each path segment individually.
   *
   * @param string $alias
   *   The alias to clean, e.g. '/Interoperabel Europees/news/foo'.
   * @param string $langcode
   *   The alias language code.
   *
   * @return string
   *   The cleaned alias.
   */
  public function cleanPath(string $alias, string $langcode): string {
    $segments = array_map(
      fn (string $segment): string => $this->aliasCleaner->cleanString($segment, ['langcode' => $langcode]),
      explode('/', trim($alias, '/')),
    );
    return $this->aliasCleaner->cleanAlias('/' . implode('/', $segments));
  }

}
