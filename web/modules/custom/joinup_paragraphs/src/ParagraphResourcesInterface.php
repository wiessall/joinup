<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs;

/**
 * Provide an interface for entities that contains resources field.
 */
interface ParagraphResourcesInterface {

  /**
   * Gets links and files from resources paragraphs.
   *
   * @return array
   *   Returns array of paragraphs resources;
   */
  public function getResources(): array;

}
