<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_tmgmt\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\joinup_tmgmt\ExtractedDataMemo;
use Drupal\joinup_tmgmt\Tmgmt\JoinupContentEntitySource;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\Tests\tmgmt_content\Kernel\ContentEntityTestBase;
use Drupal\tmgmt\Entity\Job;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that translatable data is extracted once per entity and request.
 *
 * @see \Drupal\joinup_tmgmt\ExtractedDataMemo
 * @see \Drupal\joinup_tmgmt\Tmgmt\JoinupContentEntitySource
 */
#[Group('joinup_tmgmt')]
class ExtractedDataMemoTest extends ContentEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'joinup_tmgmt',
    'node',
    'entity_reference_revisions',
    'paragraphs',
    'file',
    'path',
    'path_alias',
    'token',
    'pathauto',
  ];

  /**
   * {@inheritdoc}
   */
  protected $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    if ($container->hasDefinition(ExtractedDataMemo::class)) {
      $container->getDefinition(ExtractedDataMemo::class)->setClass(CountingExtractedDataMemo::class);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('path_alias');
    $this->installConfig(['pathauto']);
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->installEntitySchema('paragraph');
    ParagraphsType::create(['label' => 'Text', 'id' => 'text'])->save();

    FieldStorageConfig::create([
      'field_name' => 'text',
      'entity_type' => 'paragraph',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'text',
      'entity_type' => 'paragraph',
      'bundle' => 'text',
      'translatable' => TRUE,
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'paragraph_reference',
      'entity_type' => 'node',
      'type' => 'entity_reference_revisions',
      'settings' => ['target_type' => 'paragraph'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'paragraph_reference',
      'entity_type' => 'node',
      'bundle' => 'article',
    ])->save();

    $content_translation_manager = \Drupal::service('content_translation.manager');
    $content_translation_manager->setEnabled('node', 'article', TRUE);
    $content_translation_manager->setEnabled('paragraph', 'text', TRUE);
  }

  /**
   * Tests that the content source plugin is the one that keeps its results.
   */
  public function testTheSourcePluginIsReplaced(): void {
    $plugin = \Drupal::service('plugin.manager.tmgmt.source')->createInstance('content');

    $this->assertInstanceOf(JoinupContentEntitySource::class, $plugin);
  }

  /**
   * Tests that extracting the same entity twice walks it once.
   */
  public function testRepeatedExtractionWalksOnce(): void {
    [$node, $paragraph] = $this->createArticle();

    $this->extract($node);
    $this->assertSame(1, $this->walks($node));
    $this->assertSame(1, $this->walks($paragraph), 'The paragraph is walked as part of its parent.');

    $this->extract($node);
    $this->assertSame(1, $this->walks($node));
    $this->assertSame(1, $this->walks($paragraph));
  }

  /**
   * Tests that an embedded entity is not kept on its own.
   */
  public function testEmbeddedEntitiesAreNotKept(): void {
    [$node, $paragraph] = $this->createArticle();

    $this->extract($node);
    $this->assertSame(1, $this->walks($paragraph));

    $this->extract($paragraph);
    $this->assertSame(2, $this->walks($paragraph));
  }

  /**
   * Tests that a translation of an entity shares the kept result.
   */
  public function testTranslationSharesTheKeptResult(): void {
    [$node] = $this->createArticle();

    $this->extract($node);
    $this->extract($node->getTranslation('en'));
    $this->assertSame(1, $this->walks($node));

    $node->addTranslation('de', $node->toArray())->save();

    $this->extract($node->getTranslation('de'));
    $this->assertSame(2, $this->walks($node), 'Another language is extracted on its own.');
    $this->extract($node->getTranslation('de'));
    $this->assertSame(2, $this->walks($node));
  }

  /**
   * Tests that saving the entity discards what was kept for it.
   */
  public function testSavingTheEntityDiscardsTheKeptResult(): void {
    [$node] = $this->createArticle();

    $this->extract($node);
    $node->set('title', 'Changed title');
    $node->save();

    $data = $this->extract($node);
    $this->assertSame(2, $this->walks($node));
    $this->assertSame('Changed title', $data['title'][0]['value']['#text']);
  }

  /**
   * Tests that saving an embedded entity discards its parent's kept result.
   */
  public function testSavingAnEmbeddedEntityDiscardsTheKeptResult(): void {
    [$node, $paragraph] = $this->createArticle();

    $this->extract($node);
    $paragraph->set('text', 'Changed paragraph');
    $paragraph->save();

    $this->extract($node);
    $this->assertSame(2, $this->walks($node));
  }

  /**
   * Tests that only the entity extracted last keeps its result.
   */
  public function testOnlyTheLastEntityIsKept(): void {
    [$first] = $this->createArticle();
    [$second] = $this->createArticle();

    $this->extract($first);
    $this->extract($second);
    $this->extract($first);

    $this->assertSame(2, $this->walks($first), 'The first entity was displaced by the second.');
    $this->assertSame(1, $this->walks($second));
  }

  /**
   * Tests that what is derived while something is written is not kept.
   */
  public function testWhatIsDerivedWhileWritingIsNotKept(): void {
    [$node, $paragraph] = $this->createArticle();
    $memo = $this->memo();

    $memo->remember($node, function () use ($paragraph): array {
      $paragraph->save();

      return [];
    });
    $memo->remember($node, fn(): array => []);

    $this->assertSame(2, $this->walks($node));
  }

  /**
   * Tests that a save walks the source once, however many job items it has.
   */
  public function testSavingWalksTheSourceOnce(): void {
    [$node] = $this->createArticle();

    foreach (['de', 'cs'] as $target_langcode) {
      $job = tmgmt_job_create('en', $target_langcode);
      $job->translator = 'test_translator';
      $job->save();
      tmgmt_job_item_create('content', 'node', $node->id(), ['tjid' => $job->id()])->save();
    }
    $continuous = tmgmt_job_create('en', 'de', 0, [
      'job_type' => Job::TYPE_CONTINUOUS,
      'translator' => 'test_translator',
      'continuous_settings' => [
        'content' => ['node' => ['enabled' => 1, 'bundles' => ['article' => 1]]],
      ],
    ]);
    $continuous->save();

    $this->memo()->resetDerivations();
    $node->set('title', 'Changed title');
    $node->save();

    $this->assertSame(1, $this->walks($node));
  }

  /**
   * Creates an article with one referenced paragraph.
   *
   * @return array
   *   The node and the paragraph it references.
   */
  protected function createArticle(): array {
    $paragraph = Paragraph::create([
      'type' => 'text',
      'text' => 'Source paragraph',
    ]);
    $paragraph->save();

    $node = Node::create([
      'langcode' => 'en',
      'type' => 'article',
      'title' => 'Source node',
      'paragraph_reference' => $paragraph,
    ]);
    $node->save();

    return [$node, $paragraph];
  }

  /**
   * Extracts the translatable data of an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to extract.
   *
   * @return array
   *   The extracted data.
   */
  protected function extract(ContentEntityInterface $entity): array {
    return \Drupal::service('plugin.manager.tmgmt.source')
      ->createInstance('content')
      ->extractTranslatableData($entity);
  }

  /**
   * Returns how often an entity was walked.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return int
   *   The number of walks.
   */
  protected function walks(ContentEntityInterface $entity): int {
    return $this->memo()->derivations($entity);
  }

  /**
   * Returns the memo, counting the values it derives.
   *
   * @return \Drupal\Tests\joinup_tmgmt\Kernel\CountingExtractedDataMemo
   *   The memo.
   */
  protected function memo(): CountingExtractedDataMemo {
    $memo = $this->container->get(ExtractedDataMemo::class);
    assert($memo instanceof CountingExtractedDataMemo);

    return $memo;
  }

}
