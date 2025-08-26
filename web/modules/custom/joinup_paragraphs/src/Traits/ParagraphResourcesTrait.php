<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Traits;

/**
 * Shared code for nodes including paragraph reference.
 */
trait ParagraphResourcesTrait {

  /**
   * Gets links and files from resources paragraphs.
   *
   * @return array
   *   Returns array of paragraphs resources;
   */
  public function getResources(): array {
    if (!isset($this->resources)) {
      throw new \RuntimeException('Entity has no resources field.');
    }
    $resources = [];
    foreach ($this->resources->referencedEntities() as $paragraph) {
      if ($paragraph->hasField('remote_file_url')) {
        $resources[] = $paragraph->remote_file_url->first();
      }
      elseif ($paragraph->hasField('resource_file')) {
        $resources[] = $paragraph->resource_file->entity;
      }
    }
    return $resources;
  }

}
