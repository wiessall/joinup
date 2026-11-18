<?php

declare(strict_types=1);

namespace Drupal\Tests\import_ird_jsons\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\import_ird_jsons\Plugin\migrate\process\EntityFuzzyLookup;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * The failures here are silent: content linked to the wrong organisation, a re-import that links differently,
 * a duplicate organisation created next to an existing one.
 */
#[CoversClass(EntityFuzzyLookup::class)]
#[Group('import_ird_jsons')]
#[RunTestsInSeparateProcesses]
final class EntityFuzzyLookupTest extends KernelTestBase {

  protected static $modules = ['system', 'user', 'node', 'field', 'filter', 'text', 'migrate', 'migrate_plus', 'import_ird_jsons'];

  /**
   *
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['node']);
    NodeType::create(['type' => 'org', 'name' => 'Org'])->save();
  }

  /**
   *
   */
  private function org(string $title): string {
    $node = Node::create(['type' => 'org', 'title' => $title]);
    $node->save();
    return (string) $node->id();
  }

  /**
   *
   */
  private function resolve(string $input, array $config = [], ?MigrationInterface $migration = NULL): ?string {
    $plugin = $this->container->get('plugin.manager.migrate.process')->createInstance('entity_fuzzy_lookup', $config + [
      'entity_type' => 'node',
      'bundle' => 'org',
    ], $migration);
    return $plugin->transform($input, $this->createMock(MigrateExecutableInterface::class), new Row(), 'owner');
  }

  /**
   *
   */
  private function orgCount(): int {
    return (int) \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'org')->count()->execute();
  }

  /**
   * A typo matches once, and keeps matching the same entity after a closer-looking one appears.
   */
  public function testFuzzyMatchIsRemembered(): void {
    $potato = $this->org('International Potato Center');
    $this->assertSame($potato, $this->resolve('Intenational Potato Centre'));

    $this->org('Intenational Potato Centre');
    $this->assertSame($potato, $this->resolve('Intenational Potato Centre'), 'A re-import links the way it did before.');
  }

  /**
   * Generate creates an entity only when nothing is close, and only once per name.
   */
  public function testGenerateOnlyOnRealMiss(): void {
    $potato = $this->org('International Potato Center');
    $generate = ['on_miss' => 'generate'];

    $this->assertSame($potato, $this->resolve('INTERNATIONAL POTATO CENTER (CIP)', $generate));
    $this->assertSame(1, $this->orgCount());

    $new = $this->resolve('Brand New Seed Cooperative', $generate);
    $this->assertNotNull($new);
    $this->assertSame(2, $this->orgCount());
    $this->assertSame($new, $this->resolve('Brand New Seed Cooperative', $generate));
    $this->assertSame(2, $this->orgCount());
  }

  /**
   * A deleted stub was rejected: it is not generated again. A deleted match is resolved again.
   */
  public function testDeletedStubIsNotRegenerated(): void {
    $generate = ['on_miss' => 'generate'];
    $junk = $this->resolve('Industry investors', $generate);
    Node::load($junk)->delete();
    $this->assertNull($this->resolve('Industry investors', $generate));
    $this->assertSame(0, $this->orgCount());

    $old = $this->org('International Potato Center');
    $this->assertSame($old, $this->resolve('Intenational Potato Centre', $generate));
    Node::load($old)->delete();
    $new = $this->org('International Potato Center');
    $this->assertSame($new, $this->resolve('Intenational Potato Centre', $generate));
    $this->assertSame(1, $this->orgCount());
  }

  /**
   * Rolling back the migration that generated a stub forgets it, so a deleted stub can be generated again.
   * Another migration's rollback does not.
   */
  public function testRollbackForgetsGeneratedStubs(): void {
    $generate = ['on_miss' => 'generate'];
    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('id')->willReturn('test003');
    $other = $this->createMock(MigrationInterface::class);
    $other->method('id')->willReturn('other');

    Node::load($this->resolve('Soil Doctors', $generate, $migration))->delete();
    $this->assertNull($this->resolve('Soil Doctors', $generate, $migration));

    $dispatcher = $this->container->get('event_dispatcher');
    $dispatcher->dispatch(new MigrateRollbackEvent($other), MigrateEvents::POST_ROLLBACK);
    $this->assertNull($this->resolve('Soil Doctors', $generate, $migration));

    $dispatcher->dispatch(new MigrateRollbackEvent($migration), MigrateEvents::POST_ROLLBACK);
    $this->assertNotNull($this->resolve('Soil Doctors', $generate, $migration));
    $this->assertSame(1, $this->orgCount());
  }

  /**
   * The LLM can only pick one of the candidates it was shown; its answer, also "none", is asked once.
   */
  public function testLlmCannotPickOutsideCandidates(): void {
    $this->org('Department of Agriculture (Thailand)');
    $this->org('Department of Agriculture (Philippines)');
    $llm = ['llm_model' => 'test', 'llm_url' => 'http://llm'];

    // Two answers queued: no other names, then an id that was not among the candidates. A further request would
    // find the queue empty and throw, so the second resolve() also proves the answer was remembered.
    $this->mockLlm(['{"names":[]}', '{"id":"999"}']);
    $this->assertNull($this->resolve('Department of Agriculture', $llm));
    $this->assertNull($this->resolve('Department of Agriculture', $llm));
    $this->assertSame(2, $this->orgCount());
  }

  /**
   * A valid LLM choice is used.
   */
  public function testLlmChoiceIsUsed(): void {
    $this->org('Department of Agriculture (Thailand)');
    $philippines = $this->org('Department of Agriculture (Philippines)');
    $this->mockLlm(['{"names":[]}', '{"id":"' . $philippines . '"}']);
    $this->assertSame($philippines, $this->resolve('Department of Agriculture', ['llm_model' => 'test', 'llm_url' => 'http://llm']));
  }

  /**
   * A translated name finds no candidate by itself; the model's other names bring one, and it then chooses it.
   */
  public function testTranslationFindsCandidateThroughOtherNames(): void {
    $wfp = $this->org('World Food Programme');
    $this->org('World Health Organization');
    $this->mockLlm(['{"names":["World Food Programme","WFP"]}', '{"id":"' . $wfp . '"}']);
    $this->assertSame($wfp, $this->resolve('Programme Alimentaire Mondial', ['llm_model' => 'test', 'llm_url' => 'http://llm']));
  }

  /**
   * A made-up other name does not link anything by itself: the choice step still has to pick it.
   */
  public function testOtherNamesAloneDoNotLink(): void {
    $this->org('World Food Programme');
    $this->mockLlm(['{"names":["World Food Programme"]}', '{"id":"none"}']);
    $this->assertNull($this->resolve('Biochar', ['llm_model' => 'test', 'llm_url' => 'http://llm']));
  }

  /**
   * A failed call is not an answer: nothing is remembered, and the next run asks again.
   */
  public function testFailedLlmCallIsNotRemembered(): void {
    $this->org('Department of Agriculture (Thailand)');
    $philippines = $this->org('Department of Agriculture (Philippines)');
    $llm = ['llm_model' => 'test', 'llm_url' => 'http://llm'];

    $this->mockLlm([new Response(500)]);
    $this->assertNull($this->resolve('Department of Agriculture', $llm));

    $this->mockLlm(['{"names":[]}', '{"id":"' . $philippines . '"}']);
    $this->assertSame($philippines, $this->resolve('Department of Agriculture', $llm));
  }

  /**
   * @param array<string|\Psr\Http\Message\ResponseInterface> $answers
   *   The model's JSON answers, in order, or ready-made responses (e.g. a server error).
   */
  private function mockLlm(array $answers): void {
    $responses = array_map(fn($a) => $a instanceof Response ? $a
      : new Response(200, [], json_encode(['message' => ['content' => $a]])), $answers);
    $this->container->set('http_client', new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
  }

}
