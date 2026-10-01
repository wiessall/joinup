<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\sti_rdf\Controller\IdResolverController;
use Drupal\sti_rdf\Event\UriResolveEvent;
use Drupal\sti_rdf\StiUri;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests dereferencing of /id/{type}/{uuid}.
 *
 * The silent failures here are a wrong status (200 or 302 where linked-data
 * clients expect 303), a redirect that confirms an unpublished record exists,
 * and a replaced innovation whose URI quietly stops resolving.
 */
#[CoversClass(IdResolverController::class)]
#[Group('sti_rdf')]
final class IdResolverTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'serialization',
    'rdf_sync',
    'sti_rdf',
  ];

  /**
   * A published, viewable source record.
   */
  private NodeInterface $published;

  /**
   * An unpublished source record the visitor may not view.
   */
  private NodeInterface $unpublished;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('rdf_sync', ['rdf_sync_uri']);
    $this->installConfig(['system', 'user', 'node']);

    NodeType::create(['type' => 'solution', 'name' => 'Solution'])->save();
    $this->published = Node::create(['type' => 'solution', 'title' => 'Published', 'status' => 1]);
    $this->published->save();
    $this->unpublished = Node::create(['type' => 'solution', 'title' => 'Draft', 'status' => 0]);
    $this->unpublished->save();

    foreach ([$this->published, $this->unpublished] as $node) {
      $this->container->get('database')->insert('rdf_sync_uri')->fields([
        'entity_type' => 'node',
        'entity_id' => $node->id(),
        'bundle' => 'solution',
        'uri' => StiUri::forEntity('source-record', $node->uuid()),
      ])->execute();
    }

    // Map the bundle only now, in raw config, so that saving the nodes above
    // never reached for a triplestore.
    $config = $this->config('node.type.solution');
    $config->set('third_party_settings.rdf_sync', [
      'type' => 'https://sti-portal.fao.org/ontology#SourceRecord',
      'uri_plugin' => 'sti_namespace:source-record',
      'fields' => [],
    ]);
    $config->set('dependencies.module', ['rdf_sync']);
    $config->save();
    $this->container->get('entity_type.manager')->getStorage('node_type')->resetCache(['solution']);

    // The uid 1 account bypasses node access. Burn it, so that the visitor is
    // an ordinary user who may view published content and nothing more.
    $this->createUser();
    $this->setUpCurrentUser([], ['access content']);
  }

  /**
   * A resolvable URI answers 303 See Other to the entity's page.
   */
  public function testResolvesTo303(): void {
    $response = $this->get('source-record', $this->published->uuid());
    $this->assertSame(303, $response->getStatusCode());
    $this->assertStringEndsWith('/node/' . $this->published->id(), (string) $response->headers->get('Location'));
  }

  /**
   * Records the visitor may not see are indistinguishable from unknown ones.
   */
  public function testUnviewableAndUnknownAre404(): void {
    $this->assertSame(404, $this->get('source-record', $this->unpublished->uuid())->getStatusCode());
    $this->assertSame(404, $this->get('source-record', $this->container->get('uuid')->generate())->getStatusCode());
    // The right UUID under the wrong type is a different URI, and unknown.
    $this->assertSame(404, $this->get('innovation', $this->published->uuid())->getStatusCode());
  }

  /**
   * A superseded URI answers 301 to its replacement's instance URI.
   */
  public function testReplacementIsPermanentRedirect(): void {
    $replaced = $this->container->get('uuid')->generate();
    $survivor = StiUri::forEntity('innovation', $this->published->uuid());
    $this->container->get('event_dispatcher')->addListener(
      UriResolveEvent::class,
      static function (UriResolveEvent $event) use ($replaced, $survivor): void {
        if ($event->uri === StiUri::forEntity('innovation', $replaced)) {
          $event->setReplacementUri($survivor);
        }
      },
    );

    $response = $this->get('innovation', $replaced);
    $this->assertSame(301, $response->getStatusCode());
    $this->assertStringEndsWith('/id/innovation/' . $this->published->uuid(), (string) $response->headers->get('Location'));
  }

  /**
   * Requests an instance URI through the HTTP kernel.
   */
  private function get(string $type, string $uuid): Response {
    $request = Request::create("/id/$type/$uuid");
    return $this->container->get('http_kernel')->handle($request);
  }

}
