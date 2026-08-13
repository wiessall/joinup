<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_tmgmt\Kernel;

use Drupal\joinup_tmgmt\Hook\JoinupTmgmtHooks;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\tmgmt_content\Kernel\ContentEntityTestBase;
use Drupal\Tests\Traits\Core\PathAliasTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that TMGMT-mangled URL aliases are cleaned up on presave.
 *
 * @see \Drupal\joinup_tmgmt\Hook\JoinupTmgmtHooks::tmgmtContentPresaveTranslation()
 */
#[Group('joinup_tmgmt')]
class TmgmtContentPresaveTranslationTest extends ContentEntityTestBase {

  use PathAliasTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['joinup_tmgmt', 'node', 'path', 'path_alias', 'token', 'pathauto'];

  /**
   * {@inheritdoc}
   */
  protected $entityTypeId = 'node';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('path_alias');
    $this->installConfig(['pathauto']);

    $type = NodeType::create(['type' => 'page', 'name' => 'Page']);
    $type->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'page', TRUE);
  }

  /**
   * A mistranslated manual alias is cleaned segment by segment.
   */
  public function testAliasIsCleanedWhenTranslated(): void {
    $node = Node::create([
      'langcode' => 'en',
      'type' => 'page',
      'uid' => 1,
      'title' => 'Test node',
      'path' => ['alias' => '/foo/bar-baz', 'pathauto' => FALSE],
    ]);
    $node->save();

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), ['tjid' => $job->id()]);
    $job_item->save();
    $job->requestTranslation();
    $items = $job->getItems();
    reset($items)->acceptTranslation();

    $translation = Node::load($node->id())->getTranslation('de');

    $alias_cleaner = $this->container->get('pathauto.alias_cleaner');
    $expected = $alias_cleaner->cleanAlias('/' . implode('/', array_map(
      fn (string $segment): string => $alias_cleaner->cleanString($segment, ['langcode' => 'de']),
      explode('/', trim('de(de-ch): /foo/bar-baz', '/')),
    )));

    $this->assertEquals($expected, $translation->get('path')->alias);
    $this->assertStringContainsString('/', trim($translation->get('path')->alias, '/'), 'The subdirectory is preserved.');
  }

  /**
   * A translation matching the already-persisted alias is left untouched.
   */
  public function testAliasSkippedWhenUnchanged(): void {
    $node = Node::create([
      'langcode' => 'en',
      'type' => 'page',
      'uid' => 1,
      'title' => 'Test node',
      'path' => ['alias' => '/foo/bar-baz', 'pathauto' => FALSE],
    ]);
    $node->save();

    $translation = $node->addTranslation('de', $node->toArray());
    $translation->path->alias = 'de(de-ch): /foo/bar-baz';

    $this->createPathAlias('/node/' . $node->id(), 'de(de-ch): /foo/bar-baz', 'de');

    $job = tmgmt_job_create('en', 'de');
    $job->translator = 'test_translator';
    $job->save();
    $job_item = tmgmt_job_item_create('content', 'node', $node->id(), ['tjid' => $job->id()]);
    $job_item->save();

    $data = ['path' => [0 => ['alias' => ['#translate' => TRUE]]]];
    \Drupal::classResolver(JoinupTmgmtHooks::class)
      ->tmgmtContentPresaveTranslation($translation, $node, $data, $job_item);

    $this->assertEquals('de(de-ch): /foo/bar-baz', $translation->path->alias);
  }

}
