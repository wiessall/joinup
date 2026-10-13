<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\sti_rdf\StiUri;

/**
 * Lets other modules say that an instance URI now lives elsewhere.
 *
 * Dispatched by the /id/{type}/{uuid} resolver before it looks the URI up.
 * A subscriber that knows the resource was superseded (sti_dedupe, when a
 * reviewer folds one ATIO innovation into another) sets a replacement, and
 * the resolver answers 301 to the replacement's own instance URI. The old
 * URI therefore keeps resolving after the resource it named is unpublished.
 */
final class UriResolveEvent extends Event {

  /**
   * The URI that supersedes the requested one, if any.
   */
  private ?string $replacementUri = NULL;

  public function __construct(
    public readonly string $uri,
  ) {}

  /**
   * Declares the URI that supersedes the requested one.
   *
   * @throws \InvalidArgumentException
   *   When the replacement is not an STI instance URI, or is the URI itself.
   */
  public function setReplacementUri(string $uri): void {
    if (StiUri::parse($uri) === NULL) {
      throw new \InvalidArgumentException(sprintf('Replacement "%s" is not an STI instance URI.', $uri));
    }
    if ($uri === $this->uri) {
      throw new \InvalidArgumentException('A URI cannot replace itself.');
    }
    $this->replacementUri = $uri;
    $this->stopPropagation();
  }

  /**
   * Returns the superseding URI, if a subscriber set one.
   */
  public function getReplacementUri(): ?string {
    return $this->replacementUri;
  }

}
