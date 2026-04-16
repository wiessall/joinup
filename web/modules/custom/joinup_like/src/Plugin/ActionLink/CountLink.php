<?php

declare(strict_types=1);

namespace Drupal\joinup_like\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\ActionLink\ActionLinkTypePluginInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag_count\Plugin\ActionLink\CountLink as OriginalCountLink;
use Drupal\joinup_workflow\ArchivableEntityInterface;

/**
 * Provides and alternative Count link type.
 *
 * This class is an extension of the Ajax link type, but modified to allow
 * entity archival.
 */
class CountLink extends OriginalCountLink {

  /**
   * {@inheritdoc}
   */
  public function getAsFlagLink(FlagInterface $flag, EntityInterface $entity, ?string $view_mode = NULL): array {
    assert(is_subclass_of($this, ActionLinkTypePluginInterface::class), __CLASS__ . ' is intended to be used in action link plugin.');

    if ($entity instanceof ArchivableEntityInterface && $entity->isArchived()) {
      return [
        '#theme' => 'flag_count_archived',
        '#flag' => $flag,
        '#flaggable' => $entity,
        '#view_mode' => $view_mode,
        '#cache' => [
          'tags' => $entity->getCacheTags(),
        ],
      ];
    }

    return parent::getAsFlagLink($flag, $entity, $view_mode);
  }

}
