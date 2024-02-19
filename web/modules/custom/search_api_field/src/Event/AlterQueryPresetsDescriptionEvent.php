<?php

declare(strict_types=1);

namespace Drupal\search_api_field\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\search_api\IndexInterface;

/**
 * Event that is fired when the description of a query preset is altered.
 */
class AlterQueryPresetsDescriptionEvent extends Event {

  /**
   * Constructs a new AlterQueryPresetsDescriptionEvent object.
   *
   * @param array $description
   *   The description of the query presets.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search API index.
   */
  public function __construct(protected array $description, protected IndexInterface $index) {}

  /**
   * Returns the description of the query presets.
   *
   * @return array
   *   The description of the query presets.
   */
  public function getDescription(): array {
    return $this->description;
  }

  /**
   * Sets the description of the query presets.
   *
   * @param array $description
   *   The description of the query presets.
   *
   * @return $this
   */
  public function setDescription(array $description): self {
    $this->description = $description;
    return $this;
  }

  /**
   * Returns the index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The search API index.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

}
