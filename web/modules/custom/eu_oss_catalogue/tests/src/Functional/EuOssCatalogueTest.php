<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\eu_oss_catalogue\Traits\EntityToArrayTrait;
use Drupal\Tests\eu_oss_catalogue\Traits\EuOssCatalogueTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drush\TestTraits\DrushTestTrait;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class EuOssCatalogueTest extends BrowserTestBase {

  use DrushTestTrait;
  use NodeCreationTrait;
  use EntityToArrayTrait;
  use EuOssCatalogueTrait;

  /**
   * Properties to exclude when checking node content.
   *
   * @const string[]
   */
  protected const NODE_CHECK_EXCLUDED_PROPERTIES = [
    'changed',
    'created',
    'nid',
    'oss_publiccode_yml_content',
    'revision_log',
    'revision_timestamp',
    'revision_uid',
    'uuid',
    'vid',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'eu_oss_catalogue_test',
    'language',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // On install, eu_oss_catalogue is creating the controlled vocabularies. We
    // create only few testing terms.
    $this->createTestingTerms([
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
    ]);
    // Add Italian and German languages.
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
  }

  /**
   * @covers
   */
  public function testDevelopersItalia(): void {
    $service = \Drupal::getContainer()->get('eu_oss_catalogue');
    $service->fetch('developers_italia');

    // Check how incoming data has been queued. Only one item is active.
    $queue = \Drupal::queue('eu_oss_catalogue');
    // The queue contains the 2 solutions and the cleanup step.
    $this->assertSame(3, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    $node1 = $this->getNodeByTitle('Solution 1');
    $this->assertInstanceOf(OssSolutionInterface::class, $node1);
    $this->assertInstanceOf(TranslatableInterface::class, $node1);
    $node1ExpectedUntranslated = [
      'langcode' => [['value' => 'en']],
      'type' => [['target_id' => 'oss_solution']],
      'status' => [['value' => '1']],
      'uid' => ['Anonymous'],
      'title' => [['value' => 'Solution 1']],
      'promote' => [['value' => '1']],
      'sticky' => [['value' => '0']],
      'default_langcode' => [['value' => '1']],
      'revision_default' => [['value' => '1']],
      'revision_translation_affected' => [['value' => '1']],
      'oss_available_languages' => [
        ['value' => 'it'],
        ['value' => 'en'],
        ['value' => 'de'],
      ],
      'oss_categories' => ['data-collection', 'digital-citizenship'],
      'oss_development_status' => [['value' => 'stable']],
      'oss_documentation' => [],
      'oss_features' => [
        ['value' => '[EN] Solution 1: Feature 1'],
        ['value' => '[EN] Solution 1: Feature 2'],
        ['value' => '[EN] Solution 1: Feature 3'],
      ],
      'oss_git_description' => [],
      'oss_git_url' => [
        [
          'uri' => 'http://example.com/vendor/solution1',
          'title' => NULL,
          'options' => [],
        ],
      ],
      'oss_landing_url' => [],
      'oss_license' => ['AGPL-3.0-or-later'],
      'oss_logo' => [
        [
          'uri' => 'http://example.com/vendor/solution1/images/the-logo.svg',
          'title' => NULL,
          'options' => [],
        ],
      ],
      'oss_long_description' => [
        ['value' => '[EN] Solution 1: Long description'],
      ],
      'oss_maintenance_contacts' => ['Peter Gregory'],
      'oss_maintenance_contractors' => ['ACME Ltd', 'Das Auto GmbH'],
      'oss_maintenance_type' => [['value' => 'contract']],
      'oss_owner' => [['value' => 'Sin City']],
      'oss_platforms' => ['web'],
      'oss_publiccode_yml_version' => [['value' => '0.2']],
      'oss_release_date' => [['value' => '2023-07-07']],
      'oss_scope' => [],
      'oss_screenshots' => [
        [
          'uri' => 'Solution_1_EN_Screenshot1.jpg',
          'title' => NULL,
          'options' => [],
        ],
      ],
      'oss_short_description' => [
        ['value' => '[EN] Solution 1: Short description'],
      ],
      'oss_software_type' => [['value' => 'standalone/web']],
      'oss_software_version' => [['value' => '1.0.0.0']],
      'oss_source' => [
        [
          'plugin_id' => 'developers_italia',
          'configuration' => [],
        ],
      ],
      'oss_source_hash' => [
        ['value' => '09a3S4w6u61HZh5hldwk7RVXGmkTEpgqBJQiIDtKjzE'],
      ],
      'oss_used_by' => [],
    ];

    $this->assertEquals(
      [
        'en' => $node1ExpectedUntranslated,
        'de' => [
          'langcode' => [['value' => 'de']],
          'default_langcode' => [['value' => '0']],
          'oss_features' => [
            ['value' => '[DE] Solution 1: Feature 1'],
          ],
          'oss_long_description' => [
            ['value' => '[DE] Solution 1: Long description'],
          ],
          'oss_screenshots' => [
            [
              'uri' => 'Solution_1_DE_Screenshot1.jpg',
              'title' => NULL,
              'options' => [],
            ],
          ],
          'oss_short_description' => [
            ['value' => '[DE] Solution 1: Short description'],
          ],
        ] + $node1ExpectedUntranslated,
        'it' => [
          'langcode' => [['value' => 'it']],
          'default_langcode' => [['value' => '0']],
          'oss_features' => [
            ['value' => '[IT] Solution 1: Feature 1'],
            ['value' => '[IT] Solution 1: Feature 2'],
          ],
          'oss_long_description' => [
            ['value' => '[IT] Solution 1: Long description'],
          ],
          'oss_screenshots' => [
            [
              'uri' => 'Solution_1_IT_Screenshot1.jpg',
              'title' => NULL,
              'options' => [],
            ],
          ],
          'oss_short_description' => [
            ['value' => '[IT] Solution 1: Short description'],
          ],
        ] + $node1ExpectedUntranslated,
      ],
      $this->contentEntityToArray($node1, self::NODE_CHECK_EXCLUDED_PROPERTIES)
    );

    $contractor1 = $node1->get('oss_maintenance_contractors')->get(0)->entity;
    $this->assertInstanceOf(NodeInterface::class, $contractor1);
    $this->assertSame('ACME Ltd', $contractor1->label());
    $this->assertSame('2023-12-31', $contractor1->get('oss_contractor_until')->value);
    $this->assertSame('acme@example.com', $contractor1->get('oss_contractor_email')->value);
    $this->assertSame('http://www.example.com/acme', $contractor1->get('oss_contractor_website')->uri);

    $contractor2 = $node1->get('oss_maintenance_contractors')->get(1)->entity;
    $this->assertInstanceOf(NodeInterface::class, $contractor2);
    $this->assertSame('Das Auto GmbH', $contractor2->label());
    $this->assertSame('2023-11-12', $contractor2->get('oss_contractor_until')->value);
    $this->assertSame('das-auto@example.com', $contractor2->get('oss_contractor_email')->value);
    $this->assertSame('http://www.example.com/auto', $contractor2->get('oss_contractor_website')->uri);

    $contact1 = $node1->get('oss_maintenance_contacts')->get(0)->entity;
    $this->assertInstanceOf(NodeInterface::class, $contact1);
    $this->assertSame('Peter Gregory', $contact1->label());
    $this->assertSame('pieter@raviga.com', $contact1->get('oss_contact_email')->value);
    $this->assertSame('RESERVED@', $contact1->get('oss_contact_phone')->value);
    $this->assertSame('Pied Piper', $contact1->get('oss_contact_affiliation')->value);

    $node2 = $this->getNodeByTitle('Solution 2');
    $this->assertInstanceOf(OssSolutionInterface::class, $node2);
    $this->assertInstanceOf(TranslatableInterface::class, $node2);
    $node2ExpectedUntranslated = [
      'langcode' => [['value' => 'en']],
      'type' => [['target_id' => 'oss_solution']],
      'status' => [['value' => '1']],
      'uid' => ['Anonymous'],
      'title' => [['value' => 'Solution 2']],
      'promote' => [['value' => '1']],
      'sticky' => [['value' => '0']],
      'default_langcode' => [['value' => '1']],
      'revision_default' => [['value' => '1']],
      'revision_translation_affected' => [['value' => '1']],
      'oss_available_languages' => [['value' => 'it']],
      'oss_categories' => ['website-builder'],
      'oss_development_status' => [['value' => 'development']],
      'oss_documentation' => [],
      'oss_features' => [],
      'oss_git_description' => [],
      'oss_git_url' => [
        [
          'uri' => 'http://example.com/vendor/solution2',
          'title' => NULL,
          'options' => [],
        ],
      ],
      'oss_landing_url' => [
        [
          'uri' => 'http://landing.example.com/vendor/solution2',
          'title' => NULL,
          'options' => [],
        ],
      ],
      'oss_license' => ['BSD-3-Clause'],
      'oss_logo' => [],
      'oss_long_description' => [],
      'oss_maintenance_contacts' => ['FOSS Conference', 'Pirate Initiative'],
      'oss_maintenance_contractors' => [],
      'oss_maintenance_type' => [['value' => 'community']],
      'oss_owner' => [['value' => 'Narnia']],
      'oss_platforms' => ['web'],
      'oss_publiccode_yml_version' => [['value' => '0.2']],
      'oss_release_date' => [['value' => '2023-02-09']],
      'oss_scope' => [
        'energy',
        'research',
        'science-and-technology',
        'security',
      ],
      'oss_screenshots' => [],
      'oss_short_description' => [],
      'oss_software_type' => [['value' => 'library']],
      'oss_software_version' => [['value' => 'v2.3.4']],
      'oss_source' => [
        [
          'plugin_id' => 'developers_italia',
          'configuration' => [],
        ],
      ],
      'oss_source_hash' => [
        ['value' => 'p8QnKZlRwagdXkgoe5_lIDsAE9lvRhrOibFNhNrWakU'],
      ],
      'oss_used_by' => [],
    ];

    $this->assertEquals(
      [
        'en' => $node2ExpectedUntranslated,
        'it' => [
          'langcode' => [['value' => 'it']],
          'default_langcode' => [['value' => '0']],
          'oss_features' => [
            ['value' => '[IT] Solution 2: Feature 1'],
            ['value' => '[IT] Solution 2: Feature 2'],
            ['value' => '[IT] Solution 2: Feature 3'],
            ['value' => '[IT] Solution 2: Feature 4'],
          ],
          'oss_long_description' => [
            [
              'value' => <<<'EOS'
[IT] Solution 2: Long description Lorem ipsum dolor sit amet, consectetur
adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna
aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi
ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in
voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint
occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim
id est laborum.

EOS
              ,
            ],
          ],
          'oss_screenshots' => [],
          'oss_short_description' => [
            ['value' => '[IT] Solution 2: Short description Lorem ipsum'],
          ],
          'oss_documentation' => [
            [
              'uri' => 'http://it.docs.example.com/vendor/solution2',
              'title' => NULL,
              'options' => [],
            ],
          ],
        ] + $node2ExpectedUntranslated,
      ],
      $this->contentEntityToArray($node2, self::NODE_CHECK_EXCLUDED_PROPERTIES)
    );

    $contact1 = $node2->get('oss_maintenance_contacts')->get(0)->entity;
    $this->assertInstanceOf(NodeInterface::class, $contact1);
    $this->assertSame('FOSS Conference', $contact1->label());
    $this->assertSame('foss@example.com', $contact1->get('oss_contact_email')->value);
    $this->assertSame('0040675555453', $contact1->get('oss_contact_phone')->value);
    $this->assertSame('OSS Affiliation', $contact1->get('oss_contact_affiliation')->value);

    $contact2 = $node2->get('oss_maintenance_contacts')->get(1)->entity;
    $this->assertInstanceOf(NodeInterface::class, $contact2);
    $this->assertSame('Pirate Initiative', $contact2->label());
    $this->assertSame('pirate@example.com', $contact2->get('oss_contact_email')->value);
    $this->assertSame('0049689887764', $contact2->get('oss_contact_phone')->value);
    $this->assertSame('On The Sea', $contact2->get('oss_contact_affiliation')->value);

    $this->assertEquals(1, $this->countLogMessages('Solution @solution was created.', 'a:1:{s:9:"@solution";s:10:"Solution 1";}'), 'Creation log entry for Solution 1');
    $this->assertEquals(1, $this->countLogMessages('Solution @solution was created.', 'a:1:{s:9:"@solution";s:10:"Solution 2";}'), 'Creation log entry for Solution 2');

    // Re-run the process and update one solution from a new source-provider.
    $service->fetch('testing_provider');

    $queue = \Drupal::queue('eu_oss_catalogue');
    $this->assertSame(3, $queue->numberOfItems());

    // Process the queue again.
    $this->drush('queue:run', ['eu_oss_catalogue']);
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
    $service = \Drupal::getContainer()->get('eu_oss_catalogue');
    $service->fetch('removing_solution_provider');

    // Check how incoming data has been queued. Only one item is active.
    $queue = \Drupal::queue('eu_oss_catalogue');
    // The queue contains the 2 solutions and the cleanup step.
    $this->assertSame(3, $queue->numberOfItems());

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    $this->assertNotEmpty($this->getNodeByTitle('Solution 1'));
    $this->assertNotEmpty($this->getNodeByTitle('Solution 2'));

    // Re-run the process and update one solution from a new source-provider.
    $service->fetch('removing_solution_provider');
    $queue = \Drupal::queue('eu_oss_catalogue');
    // No changes, we only have the cleanup operation.
    $this->assertSame(1, $queue->numberOfItems());

    // Process the queue again.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node1 */
    $this->assertNotEmpty($this->getNodeByTitle('Solution 1'));
    $this->assertEmpty($this->getNodeByTitle('Solution 2'));

    // Test that if the solution has more than one provider it is not removed
    // due to a single provider removal.
    $service->fetch('removing_solution_provider');
    $this->drush('queue:run', ['eu_oss_catalogue']);
    $solution2 = $this->getNodeByTitle('Solution 2');
    $this->assertNotEmpty($solution2);
    $this->assertSame(1, $solution2->get('oss_source')->count());

    // Import the solutions from developers_italia plugin so that the solution
    // 2 has 2 sources.
    $service->fetch('developers_italia');
    $this->drush('queue:run', ['eu_oss_catalogue']);
    $solution2 = $this->getNodeByTitle('Solution 2', TRUE);
    $this->assertSame(2, $solution2->get('oss_source')->count());

    // Run the removal of the solution again. The solution should not be removed
    // but the source should be removed.
    $service->fetch('removing_solution_provider');
    $this->drush('queue:run', ['eu_oss_catalogue']);
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
   * Asserts that a field has a list of expected entity references.
   *
   * @param string[] $expectedLabels
   *   Expected labels.
   * @param \Drupal\Core\Field\FieldItemListInterface $field
   *   The entity reference field item list.
   */
  protected function assertReferences(array $expectedLabels, FieldItemListInterface $field): void {
    $actualLabels = array_values(array_map(fn (EntityInterface $entity): string => $entity->label(), $field->referencedEntities()));
    $this->assertEqualsCanonicalizing($expectedLabels, $actualLabels);
  }

  /**
   * Asserts that a field has a list of expected values.
   *
   * @param array $expectedValues
   *   Expected labels.
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The entity reference field item list.
   */
  protected function assertFieldValues(array $expectedValues, FieldItemListInterface $fieldItemList): void {
    $actualValues = array_values(array_map(fn (array $item) => $item['value'], $fieldItemList->getValue()));
    $this->assertSame($expectedValues, $actualValues);
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
