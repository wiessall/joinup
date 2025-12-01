<?php

declare(strict_types=1);

namespace Drupal\assessments;

/**
 * @todo Add interface description.
 */
interface ResourceParserInterface {

  /**
   * Parses inbound data from a structured array.
   *
   * @param array $data
   *   The structured data to be parsed. Passed by reference so that created
   *   entity identifiers are written back into the corresponding sub-entries
   *   under the 'id' key.
   *
   * @return array
   *   An array containing the created data and the errors if any.
   */
  public function parseInbound(array &$data): array;

}
