<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_translation\Functional;

use Drupal\Tests\eu_oss_catalogue\Functional\FetchTestBase;

/**
 * Tests auto translation .
 *
 * @group eu_oss_catalogue
 * @group eu_oss_translation
 */
class AutoTranslationTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'tmgmt',
    'tmgmt_content',
    'tmgmt_ec_etranslation',
    'tmgmt_ec_etranslation_test',
    'http_request_mock',
    'editor',
    'eu_oss_catalogue_test',
    'language',
  ];

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
   * @covers
   */
  public function testJobCreation(): void {
    $this->fetchSolutions('developers_italia', 5);
    $this->fail('fail message');
  }

}
