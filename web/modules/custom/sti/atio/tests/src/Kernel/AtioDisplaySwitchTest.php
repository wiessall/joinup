<?php

declare(strict_types=1);

namespace Drupal\Tests\atio\Kernel;

use Drupal\atio\AtioDisplaySwitch;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

/**
 * Tests the invariants of the ATIO display switch.
 *
 * Both failure modes here are silent: a missing cache context serves one
 * reader's rendering to the next, and a switch applied off the canonical page
 * changes how innovations look in listings and embeds. Neither raises an error.
 *
 * Deliberately boots only core: the switch must not depend on the Joinup
 * distribution, OG or rdf_sync.
 *
 * @group atio
 */
final class AtioDisplaySwitchTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'filter', 'text', 'node', 'atio'];

  /**
   * An ATIO innovation.
   */
  private NodeInterface $innovation;

  /**
   * A second ATIO innovation.
   */
  private NodeInterface $otherInnovation;

  /**
   * A node of an unrelated bundle.
   */
  private NodeInterface $page;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    NodeType::create(['type' => AtioDisplaySwitch::BUNDLE, 'name' => 'ATIO Innovation'])->save();
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    $this->innovation = Node::create(['type' => AtioDisplaySwitch::BUNDLE, 'title' => 'One']);
    $this->innovation->save();
    $this->otherInnovation = Node::create(['type' => AtioDisplaySwitch::BUNDLE, 'title' => 'Two']);
    $this->otherInnovation->save();
    $this->page = Node::create(['type' => 'page', 'title' => 'Page']);
    $this->page->save();
  }

  /**
   * On its own canonical page, an innovation renders in the chosen mode.
   *
   * Every variant, including the one with no query argument, must carry the
   * cache context: the no-argument variant is the one most likely to be
   * cached first.
   */
  public function testCanonicalPageSwitchesAndVaries(): void {
    $cases = [
      'no argument' => [[], 'full'],
      'both' => [['display' => 'both'], 'full'],
      'atio' => [['display' => 'atio'], 'atio_fields'],
      'claims' => [['display' => 'claims'], 'claims'],
      'unknown value' => [['display' => 'bogus'], 'full'],
      'non-scalar value' => [['display' => ['claims']], 'full'],
    ];
    foreach ($cases as $label => [$query, $expected]) {
      $this->visitCanonical($this->innovation, $query);
      $build = $this->viewFull($this->innovation);
      $this->assertSame($expected, $build['#view_mode'], "View mode for: $label");
      $this->assertContains(AtioDisplaySwitch::CACHE_CONTEXT, $build['#cache']['contexts'], "Cache context for: $label");
    }
  }

  /**
   * The switch never reaches renderings other than the page's own node.
   */
  public function testOtherRenderingsAreNotSwitched(): void {
    // Another innovation embedded on this innovation's page.
    $this->visitCanonical($this->innovation, ['display' => 'claims']);
    $this->assertSame('full', $this->viewFull($this->otherInnovation)['#view_mode']);

    // An innovation rendered on some other route, e.g. a collection listing.
    $this->visit('/collection/x', 'entity.node.canonical_other', ['display' => 'claims']);
    $this->assertSame('full', $this->viewFull($this->innovation)['#view_mode']);

    // A node of another bundle on its own page: untouched and not varied.
    $this->visitCanonical($this->page, ['display' => 'claims']);
    $build = $this->viewFull($this->page);
    $this->assertSame('full', $build['#view_mode']);
    $this->assertNotContains(AtioDisplaySwitch::CACHE_CONTEXT, $build['#cache']['contexts']);
  }

  /**
   * Builds $node in the 'full' view mode, as the canonical controller does.
   */
  private function viewFull(NodeInterface $node): array {
    return $this->container->get('entity_type.manager')->getViewBuilder('node')->view($node, 'full');
  }

  /**
   * Makes $node's canonical page the current request.
   */
  private function visitCanonical(NodeInterface $node, array $query): void {
    $this->visit('/node/' . $node->id(), 'entity.node.canonical', $query, $node);
  }

  /**
   * Pushes a request for $path, routed to $route_name, onto the stack.
   */
  private function visit(string $path, string $route_name, array $query, ?NodeInterface $node = NULL): void {
    $request = Request::create($path, 'GET', $query);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $route_name);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route($path));
    $raw = [];
    if ($node) {
      $request->attributes->set('node', $node);
      $raw['node'] = $node->id();
    }
    $request->attributes->set('_raw_variables', new InputBag($raw));
    $this->container->get('request_stack')->push($request);
  }

}
