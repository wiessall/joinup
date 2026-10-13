<?php

declare(strict_types=1);

namespace Drupal\atio;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves which of ATIO's three renderings an innovation page shows.
 *
 * An atio_innovation page can show ATIO's curated fields, the source claims
 * it was derived from, or both. The reader picks one with the ?display= query
 * argument; each choice is an ordinary view mode, so each is laid out in
 * Manage display and nowhere else:
 *
 * - both   → the 'full' request is left alone and falls back to the default
 *            display, which composes the other two.
 * - atio   → node.atio_innovation.atio_fields
 * - claims → node.atio_innovation.claims
 *
 * Only the canonical page of the node itself is switched. The same node
 * rendered elsewhere (a listing tile, a search index, an embed) keeps the view
 * mode its caller asked for.
 */
final class AtioDisplaySwitch {

  /**
   * The query argument carrying the reader's choice.
   */
  public const QUERY_ARG = 'display';

  /**
   * The cache context every switchable build must vary by.
   */
  public const CACHE_CONTEXT = 'url.query_args:' . self::QUERY_ARG;

  /**
   * The bundle this switch applies to.
   */
  public const BUNDLE = 'atio_innovation';

  /**
   * The mode used when the query argument is absent or unrecognised.
   */
  public const DEFAULT_MODE = 'both';

  /**
   * Reader-facing modes mapped to the view mode that renders them.
   *
   * NULL means "leave the requested view mode alone".
   */
  public const MODES = [
    'both' => NULL,
    'atio' => 'atio_fields',
    'claims' => 'claims',
  ];

  /**
   * View modes whose builds are, or may be, the result of a switch.
   *
   * Every atio_innovation build in one of these view modes carries
   * self::CACHE_CONTEXT, so that render-cache entries sharing the same keys
   * always vary the same way.
   */
  public const VARIED_VIEW_MODES = ['full', 'default', 'atio_fields', 'claims'];

  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Returns the reader's current mode, one of the keys of self::MODES.
   */
  public function currentMode(): string {
    // Read through all() rather than get(): InputBag::get() throws on a
    // non-scalar value, which would turn ?display[]=x into a 400 on every
    // innovation page instead of falling back to the default.
    $value = $this->requestStack->getCurrentRequest()?->query->all()[self::QUERY_ARG] ?? NULL;
    return is_string($value) && array_key_exists($value, self::MODES) ? $value : self::DEFAULT_MODE;
  }

  /**
   * Returns the view mode to render $entity in, given the requested one.
   */
  public function resolveViewMode(EntityInterface $entity, string $view_mode): string {
    if ($view_mode !== 'full' || !$this->isCanonicalPageOf($entity)) {
      return $view_mode;
    }
    return self::MODES[$this->currentMode()] ?? $view_mode;
  }

  /**
   * Whether a build of $entity in $view_mode must vary by the query argument.
   */
  public function buildVaries(EntityInterface $entity, string $view_mode): bool {
    return $this->applies($entity) && in_array($view_mode, self::VARIED_VIEW_MODES, TRUE);
  }

  /**
   * Whether $entity is an ATIO innovation.
   */
  public function applies(EntityInterface $entity): bool {
    return $entity instanceof NodeInterface && $entity->bundle() === self::BUNDLE;
  }

  /**
   * Whether the current route is the canonical page of $entity.
   */
  private function isCanonicalPageOf(EntityInterface $entity): bool {
    if (!$this->applies($entity) || $this->routeMatch->getRouteName() !== 'entity.node.canonical') {
      return FALSE;
    }
    $route_node = $this->routeMatch->getParameter('node');
    // Entity IDs come back as int or numeric string depending on how the
    // entity was loaded; compare them as strings.
    return $route_node instanceof NodeInterface && (string) $route_node->id() === (string) $entity->id();
  }

}
