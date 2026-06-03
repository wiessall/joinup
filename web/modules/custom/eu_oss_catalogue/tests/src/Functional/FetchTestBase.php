<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Variable;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\eu_oss_catalogue\Entity\OssContactInterface;
use Drupal\eu_oss_catalogue\Entity\OssContractorInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\node\NodeInterface;
use Drush\TestTraits\DrushTestTrait;

/**
 * Base class for fetch tests.
 */
abstract class FetchTestBase extends BrowserTestBase {

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
    'editor',
    'eu_oss_catalogue_test',
    'language',
    'markdown_easy',
  ];

  /**
   * Controlled vocabularies testing terms.
   *
   * @var array[]
   */
  protected array $controlledVocabTerms = [];

  /**
   * Module dir.
   */
  public ?string $dir = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    foreach ($this->controlledVocabTerms as $vid => $terms) {
      foreach ($terms as $term) {
        $termStorage->create(['vid' => $vid, 'name' => $term])->save();
      }
    }

    $settings['settings']['eu_oss_catalogue']['platform_github']['access_token'] = (object) [
      'value' => 'dummy-token',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);

    // Add Italian and German languages.
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();

    $this->dir = __DIR__;
  }

  /**
   * Asserts that an OSS Solution with a given title has expected data.
   *
   * @param string $title
   *   The OSS Solution title. This used also to retrieve the expectancy data.
   * @param string $expectedLangcode
   *   The solution's default langcode.
   */
  protected function assertOssSolution(string $title, string $expectedLangcode): void {
    $solution = $this->getNode($title, OssSolutionInterface::class);
    $this->assertSame($expectedLangcode, $solution->language()->getId());
  }

  /**
   * Asserts that an OSS Contact with a given title has expected data.
   *
   * @param string $title
   *   The OSS Contact title. This used also to retrieve the expectancy data.
   */
  protected function assertOssContact(string $title): void {
    $this->getNode($title, OssContactInterface::class);
  }

  /**
   * Asserts that an OSS Contractor with a given title has expected data.
   *
   * @param string $title
   *   The OSS Contact title. This used also to retrieve the expectancy data.
   */
  protected function assertOssContractor(string $title): void {
    $this->getNode($title, OssContractorInterface::class);
  }

  /**
   * Asserts that a node has expected data and returns it.
   *
   * @param string $nodeTitle
   *   The node title. This used also to retrieve the expectancy data.
   * @param string $interface
   *   The entity/bundle class that this node should implement.
   */
  protected function getNode(string $nodeTitle, string $interface): OssSolutionInterface|OssContactInterface|OssContractorInterface {
    // Expected values are stored in fixture YAML files, named after the calling
    // class and method. Test cases are keyed by $nodeTitle string.
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    // The first trace entry (0) is this method, the second (1) is its caller,
    // which is an assertion. The third (2) is the test method.
    $parts = explode('\\', $trace[2]['class']);
    $filePath = $this->dir . '/../../fixtures/expectancy/' . array_pop($parts) . '.' . $trace[2]['function'] . '.yml';
    $this->assertFileExists($filePath);
    $expected = Yaml::decode(file_get_contents($filePath))[$nodeTitle];
    $this->assertIsArray($expected);
    $this->assertNotEmpty($expected);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface|\Drupal\eu_oss_catalogue\Entity\OssContactInterface|\Drupal\eu_oss_catalogue\Entity\OssContractorInterface $actual */
    $actual = $this->getNodeByTitle($nodeTitle);

    $this->assertInstanceOf($interface, $actual);

    foreach ($expected as $langcode => $expectedTranslation) {
      $actualTranslation = $actual->getTranslation($langcode)->toArray();
      // Keep only what we test.
      $actualTranslation = array_intersect_key($actualTranslation, $expectedTranslation);
      $actualTranslation = $this->normalizeActualNodeArray($actualTranslation, $actual, $langcode);
      foreach ($expectedTranslation as $field => $expectedValue) {
        $actualValue = $actualTranslation[$field] ?? NULL;
        $this->assertEquals(
          $expectedValue,
          $actualValue,
          sprintf(
            "Value of field '%s' is different than expected. Expected %s, actual is %s.",
            $field,
            Variable::export($expectedValue),
            Variable::export($actualValue),
          )
        );
      }
    }

    return $actual;
  }

  /**
   * Normalizes the actual node array.
   *
   * - Convert entity reference target IDs into targeted entity label.
   * - Keeps only the main field properties and removes the property key.
   * - On cardinality 1, it transforms from array to scalar.
   *
   * @param array $nodeArray
   *   The translated node as array.
   * @param \Drupal\node\NodeInterface $node
   *   The node entity instance.
   * @param string $langcode
   *   The translation language code.
   *
   * @return array
   *   The translated node with the entity reference fields resolved.
   */
  protected function normalizeActualNodeArray(array $nodeArray, NodeInterface $node, string $langcode): array {
    $values = [];
    foreach ($nodeArray as $fieldName => $value) {
      if ($node->hasField($fieldName)) {
        $field = $node->get($fieldName);
        $storageDefinition = $field->getFieldDefinition()->getFieldStorageDefinition();
        $mainProperty = $storageDefinition->getMainPropertyName();
        if ($field instanceof EntityReferenceFieldItemListInterface) {
          $entities = $field->referencedEntities();
          $values[$fieldName] = array_map(function (int $delta) use ($entities, $langcode): string {
            $entity = $entities[$delta];
            $entity = $entity instanceof ContentEntityInterface && $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;
            return $entity->label() ?: $entity->id();
          }, array_keys($value));
        }
        else {
          $values[$fieldName] = array_map(function (array $item) use ($mainProperty) {
            return $item[$mainProperty];
          }, $value);
        }

        // Converts from array to scalar when cardinality is 1.
        if ($storageDefinition->getCardinality() == 1) {
          $values[$fieldName] = $values[$fieldName][0] ?? NULL;
        }
      }
    }
    return $values;
  }

  /**
   * Fetches from a given provider and runs the resulting queue.
   *
   * @param string $providerId
   *   The EU OSS Catalogue provider plugin ID.
   * @param int $expectedQueuedSolutions
   *   The expected queued solutions.
   */
  protected function fetchSolutions(string $providerId, int $expectedQueuedSolutions): void {
    \Drupal::service('eu_oss_catalogue')->fetch($providerId);

    // Check how incoming data has been queued. Only one item is active.
    $queue = \Drupal::queue('eu_oss_catalogue');
    // The queue contains $entriesCount solutions plus the cleanup step.
    $this->assertSame($expectedQueuedSolutions, $queue->numberOfItems() - 1, 'Number of queued solutions');

    // Check queue processing. Use Drush to run the queue.
    $this->drush('queue:run', ['eu_oss_catalogue']);

    $this->assertSame(0, $queue->numberOfItems(), 'Queue items have been released.');
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
      // Use LIKE to be able to check only the first part of serialized
      // variables blob if it's too big (e.g. if contains the entire backtrace).
      $like = $query->escapeLike($variables) . '%';
      $query->condition('variables', $like, 'LIKE');
    }

    return $query->execute()->fetchAll();
  }

}
