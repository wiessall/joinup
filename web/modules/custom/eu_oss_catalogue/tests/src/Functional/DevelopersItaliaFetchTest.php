<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Core\Database\Database;
use Drupal\Core\StringTranslation\TranslatableMarkup;
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
    'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause', 'EUPL-1.2'],
    'oss_category' => [
      'data-collection',
      'website-builder',
      'business-process-management',
      'digital-citizenship',
    ],
    'oss_intended_audience_scope' => [
      'energy',
      'research',
      'science-and-technology',
      'security',
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
    $this->fetchSolutions('developers_italia', 2);

    $this->assertNode('Solution 1', OssSolutionInterface::class);
    $this->assertNode('ACME Ltd', OssContractorInterface::class);
    $this->assertNode('Das Auto GmbH', OssContractorInterface::class);
    $this->assertNode('Peter Gregory', OssContactInterface::class);

    $this->assertNode('Solution 2', OssSolutionInterface::class);
    $this->assertNode('FOSS Conference', OssContactInterface::class);
    $this->assertNode('Pirate Initiative', OssContactInterface::class);

    $this->assertEquals(1, $this->countLogMessages('Solution @solution was created.', 'a:1:{s:9:"@solution";s:10:"Solution 1";}'), 'Creation log entry for Solution 1');
    $this->assertEquals(1, $this->countLogMessages('Solution @solution was created.', 'a:1:{s:9:"@solution";s:10:"Solution 2";}'), 'Creation log entry for Solution 2');

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('testing_provider', 2);

    $this->assertEmpty($this->getNodeByTitle('Solution 1'));
    $updated_solution = $this->getNodeByTitle('Solution 1 (updated)', TRUE);
    $this->assertSame('1.0.0.1', $updated_solution->get('oss_software_version')->value);
    $this->assertSame(
      'ZyLKKSPLDPgnEVPUPNF3m0dRw8o74nJn2MSC97TSDeo',
      $updated_solution->get('oss_source_hash')->value
    );
    // Assert that the provider has been added to the list of providers.
    $this->assertSame(2, $updated_solution->get('oss_source')->count());
    $expected_providers = ['developers_italia', 'testing_provider'];
    $this->assertSame($expected_providers, array_map(fn (array $item): string => $item['plugin_id'], $updated_solution->get('oss_source')->getValue()));

    $this->assertEquals(1, $this->countLogMessages('Solution @solution was updated.', 'a:1:{s:9:"@solution";s:20:"Solution 1 (updated)";}'), 'Update log entry for Solution 1');
    $this->assertEquals(1, $this->countLogMessages('Solution @solution was updated.', 'a:1:{s:9:"@solution";s:10:"Solution 2";}'), 'Update log entry for Solution 2');
  }

  /**
   * Tests that when a solution is removed upstream, is removed also locally.
   */
  public function testRemovedSolution(): void {
    $this->fetchSolutions('removing_solution_provider', 2);

    $this->assertNotEmpty($this->getNodeByTitle('Solution 1'));
    $this->assertNotEmpty($this->getNodeByTitle('Solution 2'));

    // Re-run the process and update one solution from a new source-provider.
    $this->fetchSolutions('removing_solution_provider', 0);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node1 */
    $this->assertNotEmpty($this->getNodeByTitle('Solution 1'));
    $this->assertEmpty($this->getNodeByTitle('Solution 2'));

    // Test that if the solution has more than one provider it is not removed
    // due to a single provider removal.
    $this->fetchSolutions('removing_solution_provider', 1);

    $solution2 = $this->getNodeByTitle('Solution 2');
    $this->assertNotEmpty($solution2);
    $this->assertSame(1, $solution2->get('oss_source')->count());

    // Import the solutions from developers_italia plugin so that the Solution 2
    // has 2 sources.
    $this->fetchSolutions('developers_italia', 2);

    $solution2 = $this->getNodeByTitle('Solution 2', TRUE);
    $this->assertSame(2, $solution2->get('oss_source')->count());

    // Run the removal of the solution again. The solution should not be removed
    // but the source should be removed.
    $this->fetchSolutions('removing_solution_provider', 0);

    $solution2 = $this->getNodeByTitle('Solution 2', TRUE);
    $this->assertNotEmpty($solution2);
    $this->assertSame(1, $solution2->get('oss_source')->count());
  }

  /**
   * Tests that solutions without mandatory values are logged as errors.
   */
  public function testMissingMandatoryValues(): void {
    $service = \Drupal::getContainer()->get('eu_oss_catalogue');
    $service->fetch('developers_italia');

    $logs = \Drupal::database()->query("SELECT message, variables FROM {watchdog} WHERE type = 'eu_oss_catalogue'")->fetchAll();
    $message = strip_tags($logs[0]->message);
    $variables = unserialize($logs[0]->variables, [
      'allowed_classes' => [
        TranslatableMarkup::class,
      ],
    ]);
    $this->assertEquals('Developers Italia', $variables['@provider']);
    $this->assertSame('developers_italia', $variables['@provider_id']);
    $this->assertSame('invalid1-0000-0000-0000-000000000000', $variables['@reference']);
    $this->assertStringContainsString('Schema errors:', $message);
    $this->assertStringContainsString('name: required but empty', $message);
    $this->assertStringContainsString('url: required but empty', $message);
    $this->assertStringContainsString('localisation/availableLanguages: required but empty', $message);
  }

  /**
   * Tests the country specific values for Italy.
   */
  public function testItCountrySpecificValues(): void {
    $service = \Drupal::getContainer()->get('eu_oss_catalogue');
    $service->fetch('developers_italia');

    // Check failed imports.
    $logs = \Drupal::database()->query("SELECT message, variables FROM {watchdog} WHERE type = 'eu_oss_catalogue'")->fetchAll();
    $message = strip_tags($logs[1]->message);
    $variables = unserialize($logs[1]->variables, [
      'allowed_classes' => [
        TranslatableMarkup::class,
      ],
    ]);
    $this->assertEquals('Developers Italia', $variables['@provider']);
    $this->assertSame('developers_italia', $variables['@provider_id']);
    $this->assertSame('invalid2-0000-0000-0000-000000000000', $variables['@reference']);
    $this->assertStringContainsString('it/conforme/modelloInteroperabilita: variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData', $message);
    $this->assertStringContainsString('it/conforme/misureMinimeSicurezza: variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData', $message);
    $this->assertStringContainsString('it/conforme/gdpr: variable type is string but applied schema class is Drupal\Core\TypedData\Plugin\DataType\BooleanData', $message);
    $this->assertStringContainsString('it/conforme/some_random_key: missing schema', $message);
    // Assert that missing keys from the mapping are not logging errors as they
    // are not required.
    $this->assertStringNotContainsString('it/piattaforme/pagopa', $message);
  }

  /**
   * Counts log messages.
   *
   * @param string $message
   *   Searched message.
   * @param string $variables
   *   Searched variables.
   *
   * @return int
   *   Number of logged messaged.
   */
  protected function countLogMessages(string $message, string $variables): int {
    return (int) Database::getConnection()
      ->select('watchdog')
      ->condition('type', 'eu_oss_catalogue')
      ->condition('message', $message)
      ->condition('variables', $variables)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

}
