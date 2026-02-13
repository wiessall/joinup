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
   * Asserts the Developers Italia fetch and update process.
   */
  protected function assertDevelopersItaliaFetch(): void {
    $this->fetchSolutions('developers_italia', 7);

    $this->assertOssSolution('GitHub solution 1', 'en');
    $this->assertOssContractor('ACME Ltd');
    $this->assertOssContractor('Das Auto GmbH');
    $this->assertOssContact('Peter Gregory');

    $this->assertOssSolution('Project 1.1', 'it');
    $this->assertOssContact('Big Brother');

    $this->assertOssSolution('Project 1.2', 'de');
    $this->assertOssContact('John Doe');
    $this->assertOssContact('Jane Roe');

    $this->assertOssSolution('Project 1.4', 'de');
    $this->assertOssSolution('Project 1', 'de');

    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:17:"GitHub solution 1";}'),
      'Creation log entry for GitHub solution 1'
    );
    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.1";}'),
      'Creation log entry for Project 1.1'
    );
    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.2";}'),
      'Creation log entry for Project 1.2'
    );
    $this->assertCount(
      1,
      $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:9:"Project 1";}'),
      'Creation log entry for Project 1'
    );

    $this->assertCount(
      2,
      $this->getLogEntries('OSS solution of project @project-name (@project-url) fetched from @provider cannot be parsed, created or saved.'),
    );

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('testing_provider', 7);

    $this->assertFalse($this->getNodeByTitle('GitHub solution 1'));
    $updatedSolution = $this->getNodeByTitle('GitHub solution 1 (updated)', TRUE);
    $this->assertSame('3.0.0', $updatedSolution->get('oss_software_version')->value);
    $this->assertTrue($updatedSolution->get('oss_maintenance_contractors')->isEmpty());
    $this->assertTrue($updatedSolution->get('oss_maintenance_contacts')->isEmpty());
    $this->assertSame('UviES-yJZ_QF-Xp_Ivd4RwxVno_qG_8nutZOqff3Vy0', $updatedSolution->get('oss_source_hash')->value);
    // Assert that the provider has been added to the list of providers.
    $this->assertSame(2, $updatedSolution->get('oss_source')->count());
    $expected_providers = ['developers_italia', 'testing_provider'];
    $this->assertSame($expected_providers, array_map(fn (array $item): string => $item['plugin_id'], $updatedSolution->get('oss_source')->getValue()));

    $this->assertStringContainsString('Update, because data has been changed in "Testing provider" source/catalogue', $updatedSolution->getRevisionLogMessage(), 'Update revision message for GitHub solution 1');
  }

}
