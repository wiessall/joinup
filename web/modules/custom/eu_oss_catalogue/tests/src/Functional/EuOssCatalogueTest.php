<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drush\TestTraits\DrushTestTrait;

/**
 * EU OSS Catalogue test.
 *
 * @group eu_oss_catalogue
 */
class EuOssCatalogueTest extends BrowserTestBase {

  use DrushTestTrait;
  use NodeCreationTrait;

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
    $this->createTestingTerms();
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

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node1 */
    $node1 = $this->getNodeByTitle('Solution 1');
    $this->assertSame('oss_solution', $node1->bundle());
    $this->assertSame('http://example.com/vendor/solution1', $node1->get('oss_git_url')->uri);
    $this->assertSame('1.0.0.0', $node1->get('oss_software_version')->value);
    $this->assertSame('0.2', $node1->get('oss_publiccode_yml_version')->value);
    $this->assertSame('2023-07-07', $node1->get('oss_release_date')->value);
    $this->assertSame('http://example.com/vendor/solution1/images/the-logo.svg', $node1->get('oss_logo')->uri);
    $this->assertReferences(['web'], $node1->get('oss_platforms'));
    $this->assertReferences(['data-collection', 'digital-citizenship'], $node1->get('oss_categories'));
    $this->assertSame('stable', $node1->get('oss_development_status')->value);
    $this->assertSame('standalone/web', $node1->get('oss_software_type')->value);
    $this->assertSame(
      '09a3S4w6u61HZh5hldwk7RVXGmkTEpgqBJQiIDtKjzE',
      $node1->get('oss_source_hash')->value
    );
    $this->assertStringContainsString('[IT] Solution 1: Long description', $node1->getTranslation('it')->get('oss_long_description')->value);
    $this->assertStringContainsString('[IT] Solution 1: Short description', $node1->getTranslation('it')->get('oss_short_description')->value);
    $this->assertStringContainsString('[EN] Solution 1: Long description', $node1->get('oss_long_description')->value);
    $this->assertStringContainsString('[EN] Solution 1: Short description', $node1->get('oss_short_description')->value);
    $this->assertStringContainsString('[DE] Solution 1: Long description', $node1->getTranslation('de')->get('oss_long_description')->value);
    $this->assertStringContainsString('[DE] Solution 1: Short description', $node1->getTranslation('de')->get('oss_short_description')->value);
    $this->assertFieldValues([
      '[IT] Solution 1: Feature 1',
      '[IT] Solution 1: Feature 2',
    ], $node1->getTranslation('it')->get('oss_features'));
    $this->assertFieldValues([
      '[EN] Solution 1: Feature 1',
      '[EN] Solution 1: Feature 2',
      '[EN] Solution 1: Feature 3',
    ], $node1->get('oss_features'));
    $this->assertFieldValues(['[DE] Solution 1: Feature 1'], $node1->getTranslation('de')->get('oss_features'));
    $this->assertReferences(['AGPL-3.0-or-later'], $node1->get('oss_license'));
    $this->assertSame('Sin City', $node1->get('oss_main_copyright_owner')->value);
    $this->assertTrue($node1->get('oss_landing_url')->isEmpty());
    $this->assertSame('contract', $node1->get('oss_maintenance_type')->value);
    /** @var \Drupal\node\NodeInterface $contractor1 */
    $contractor1 = $node1->get('oss_maintenance_contractors')->get(0)->entity;
    $this->assertSame('ACME Ltd', $contractor1->label());
    $this->assertSame('2023-12-31', $contractor1->get('oss_contractor_until')->value);
    $this->assertSame('acme@example.com', $contractor1->get('oss_contractor_email')->value);
    $this->assertSame('http://www.example.com/acme', $contractor1->get('oss_contractor_website')->uri);
    /** @var \Drupal\node\NodeInterface $contractor2 */
    $contractor2 = $node1->get('oss_maintenance_contractors')->get(1)->entity;
    $this->assertSame('Das Auto GmbH', $contractor2->label());
    $this->assertSame('2023-11-12', $contractor2->get('oss_contractor_until')->value);
    $this->assertSame('das-auto@example.com', $contractor2->get('oss_contractor_email')->value);
    $this->assertSame('http://www.example.com/auto', $contractor2->get('oss_contractor_website')->uri);
    /** @var \Drupal\node\NodeInterface $contact1 */
    $contact1 = $node1->get('oss_maintenance_contacts')->get(0)->entity;
    $this->assertSame('Peter Gregory', $contact1->label());
    $this->assertSame('pieter@raviga.com', $contact1->get('oss_contact_email')->value);
    $this->assertSame('RESERVED@', $contact1->get('oss_contact_phone')->value);
    $this->assertSame('Pied Piper', $contact1->get('oss_contact_affiliation')->value);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node2 */
    $node2 = $this->getNodeByTitle('Solution 2');
    $this->assertSame('oss_solution', $node2->bundle());
    $this->assertSame('http://example.com/vendor/solution2.git', $node2->getTranslation('it')->get('oss_git_url')->uri);
    $this->assertSame('http://landing.example.com/vendor/solution2', $node2->getTranslation('it')->get('oss_landing_url')->uri);
    $this->assertSame('v2.3.4', $node2->get('oss_software_version')->value);
    $this->assertSame('0.2', $node2->get('oss_publiccode_yml_version')->value);
    $this->assertSame('2023-02-09', $node2->get('oss_release_date')->value);
    $this->assertReferences(['web'], $node2->get('oss_platforms'));
    $this->assertReferences(['website-builder'], $node2->get('oss_categories'));
    $this->assertSame('development', $node2->get('oss_development_status')->value);
    $this->assertSame('library', $node2->get('oss_software_type')->value);
    $this->assertSame(
      'hQuCpTFLBeeOcz0U1i9JuBSiwplY_0JC8QFZ5usZOxU',
      $node2->get('oss_source_hash')->value
    );
    $this->assertStringContainsString('[IT] Solution 2: Long description', $node2->getTranslation('it')->get('oss_long_description')->value);
    $this->assertStringContainsString('[IT] Solution 2: Short description', $node2->getTranslation('it')->get('oss_short_description')->value);
    $this->assertReferences(['BSD-3-Clause'], $node2->get('oss_license'));
    $this->assertFieldValues([
      '[IT] Solution 2: Feature 1',
      '[IT] Solution 2: Feature 2',
      '[IT] Solution 2: Feature 3',
      '[IT] Solution 2: Feature 4',
    ], $node2->getTranslation('it')->get('oss_features'));
    $this->assertSame('Narnia', $node2->get('oss_main_copyright_owner')->value);
    $this->assertTrue($node2->get('oss_logo')->isEmpty());
    $this->assertSame('community', $node2->get('oss_maintenance_type')->value);
    /** @var \Drupal\node\NodeInterface $contact1 */
    $contact1 = $node2->get('oss_maintenance_contacts')->get(0)->entity;
    $this->assertSame('FOSS Conference', $contact1->label());
    $this->assertSame('foss@example.com', $contact1->get('oss_contact_email')->value);
    $this->assertSame('0040675555453', $contact1->get('oss_contact_phone')->value);
    $this->assertSame('OSS Affiliation', $contact1->get('oss_contact_affiliation')->value);
    /** @var \Drupal\node\NodeInterface $contact2 */
    $contact2 = $node2->get('oss_maintenance_contacts')->get(1)->entity;
    $this->assertSame('Pirate Initiative', $contact2->label());
    $this->assertSame('pirate@example.com', $contact2->get('oss_contact_email')->value);
    $this->assertSame('0049689887764', $contact2->get('oss_contact_phone')->value);
    $this->assertSame('On The Sea', $contact2->get('oss_contact_affiliation')->value);

    // Re-run the process and update one solution from a new source-provider.
    $service->fetch('testing_provider');

    $queue = \Drupal::queue('eu_oss_catalogue');
    $this->assertSame(3, $queue->numberOfItems());

    // Process the queue again.
    $this->drush('queue:run', ['eu_oss_catalogue']);
    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $node1 */
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
   * Creates testing terms.
   */
  protected function createTestingTerms(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $data = [
      'oss_licence' => ['AGPL-3.0-or-later', 'BSD-3-Clause', 'EUPL-1.2'],
      'oss_category' => [
        'data-collection',
        'website-builder',
        'business-process-management',
        'digital-citizenship',
      ],
    ];
    foreach ($data as $vid => $terms) {
      foreach ($terms as $term) {
        $storage->create(['vid' => $vid, 'name' => $term])->save();
      }
    }
  }

}
