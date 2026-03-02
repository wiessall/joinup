<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

/**
 * Base class for Developers Italia fetch tests.
 */
abstract class DevelopersItaliaFetchTestBase extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected array $controlledVocabTerms = [
    'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause', 'EUPL-1.2', 'MIT'],
    'oss_category' => [
      'data-analytics',
      'data-collection',
      'digital-citizenship',
      'nothing',
      'website-builder',
    ],
  ];

  /**
   * Fetches solutions from the Developers Italia provider.
   */
  protected function assertDevelopersItaliaFetch(): void {
    $this->fetchSolutions('developers_italia', 7);
  }

}
