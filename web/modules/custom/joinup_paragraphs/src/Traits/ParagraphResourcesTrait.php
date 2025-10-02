<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Traits;

use Drupal\Core\File\FileUrlGeneratorInterface;

/**
 * Shared code for nodes including paragraph reference.
 */
trait ParagraphResourcesTrait {

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
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
