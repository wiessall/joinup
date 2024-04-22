<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\Event;

use EasyRdf\Graph;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * When a vocabulary graph is being prepared.
 *
 * Subscribers can act and alter the graph.
 */
class PrepareVocabularyGraphEvent extends Event {

  public function __construct(
    public readonly string $vid,
    protected Graph $graph,
  ) {}

  /**
   * Returns the graph object.
   *
   * @return \EasyRdf\Graph
   *   The graph object.
   */
  public function getGraph(): Graph {
    return $this->graph;
  }

}
