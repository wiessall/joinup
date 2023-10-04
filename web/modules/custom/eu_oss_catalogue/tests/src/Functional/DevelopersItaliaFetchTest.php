<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Core\Database\Database;
use Drupal\eu_oss_catalogue\Entity\OssContactInterface;
use Drupal\eu_oss_catalogue\Entity\OssContractorInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class DevelopersItaliaFetchTest extends FetchTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['language'];

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Add Italian and German languages.
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * @covers
   */
  public function testDevelopersItalia(): void {
    $this->fetchSolutions('developers_italia', 4);

    $this->assertNode('GitHub solution 1', OssSolutionInterface::class);
    $this->assertNode('ACME Ltd', OssContractorInterface::class);
    $this->assertNode('Das Auto GmbH', OssContractorInterface::class);
    $this->assertNode('Peter Gregory', OssContactInterface::class);

    $this->assertNode('Project 1.1', OssSolutionInterface::class);
    $this->assertNode('Big Brother', OssContactInterface::class);

    $this->assertNode('Project 1.2', OssSolutionInterface::class);
    $this->assertNode('John Doe', OssContactInterface::class);
    $this->assertNode('Jane Roe', OssContactInterface::class);

    $this->assertNode('Project 1', OssSolutionInterface::class);
    $this->assertNode('Still The Big Brother', OssContactInterface::class);

    // Creation log entry for 'GitHub solution 1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:17:"GitHub solution 1";}'));
    // Creation log entry for 'Project 1.1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.1";}'));
    // Creation log entry for 'Project 1.2'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.2";}'));
    // Creation log entry for 'Project 1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:9:"Project 1";}'));

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('testing_provider', 4);

    $this->assertFalse($this->getNodeByTitle('GitHub solution 1'));
    $updatedSolution = $this->getNodeByTitle('GitHub solution 1 (updated)', TRUE);
    $this->assertSame('3.0.0', $updatedSolution->get('oss_software_version')->value);
    $this->assertSame('saIuPzFiosNtQhCZf91iPXVcLl66QJgRZTWo63p4PBM', $updatedSolution->get('oss_source_hash')->value);
    // Assert that the provider has been added to the list of providers.
    $this->assertSame(2, $updatedSolution->get('oss_source')->count());
    $expected_providers = ['developers_italia', 'testing_provider'];
    $this->assertSame($expected_providers, array_map(fn (array $item): string => $item['plugin_id'], $updatedSolution->get('oss_source')->getValue()));

    // Update log entry for 'GitHub solution 1 (updated)'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was updated.', 'a:1:{s:9:"@solution";s:27:"GitHub solution 1 (updated)";}'));
    // Update log entry for 'Project 1.1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.1";}'));
    // Creation log entry for 'Project 1.2'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:11:"Project 1.2";}'));
    // Creation log entry for 'Project 1'.
    $this->assertCount(1, $this->getLogEntries('Solution @solution was created.', 'a:1:{s:9:"@solution";s:9:"Project 1";}'));
  }

  /**
   * Tests that when a solution is removed upstream, is removed also locally.
   */
  public function testRemovedSolution(): void {
    $this->fetchSolutions('removing_solution_provider', 4);

    $this->assertNotFalse($this->getNodeByTitle('GitHub solution 1'));
    $this->assertNotFalse($this->getNodeByTitle('Project 1.1'));

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('removing_solution_provider', 0);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node1 */
    $this->assertNotFalse($this->getNodeByTitle('GitHub solution 1'));
    $this->assertFalse($this->getNodeByTitle('Project 1.1'));

    // Test that if the solution has more than one provider it is not removed
    // due to a single provider removal.
    $this->fetchSolutions('removing_solution_provider', 1);

    $solution2 = $this->getNodeByTitle('Project 1.1');
    $this->assertNotEmpty($solution2);
    $this->assertSame(1, $solution2->get('oss_source')->count());

    // Import the solutions from developers_italia plugin so that the Solution 2
    // has 2 sources.
    $this->fetchSolutions('developers_italia', 4);

    $solution2 = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertSame(2, $solution2->get('oss_source')->count());

    // Run the removal of the solution again. The solution should not be removed
    // but the source should be removed.
    $this->fetchSolutions('removing_solution_provider', 0);

    $solution2 = $this->getNodeByTitle('Project 1.1', TRUE);
    $this->assertNotEmpty($solution2);
    $this->assertSame(1, $solution2->get('oss_source')->count());
  }

  /**
   * Gets dblog entries.
   *
   * @param string|null $message
   *   (optional) Searched message.
   * @param string|null $variables
   *   (optional) Searched serialized variables.
   *
   * @return object[]
   *   A list of dblog entries.
   */
  protected function getLogEntries(?string $message = NULL, ?string $variables = NULL): array {
    $query = Database::getConnection()
      ->select('watchdog')
      ->fields('watchdog', ['message', 'variables'])
      ->condition('type', 'eu_oss_catalogue');

    if ($message) {
      $query->condition('message', $message);
    }
    if ($variables) {
      $query->condition('variables', $variables);
    }

    return $query->execute()->fetchAll();
  }

}
