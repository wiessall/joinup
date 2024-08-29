<?php

declare(strict_types=1);

namespace Drupal\joinup_like\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\ActionLink\ActionLinkTypePluginInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag_count\Plugin\ActionLink\CountLink as OriginalCountLink;
use Drupal\joinup_workflow\ArchivableEntityInterface;

/**
 * Provides the Count link type for node entities.
 *
 * This class is an extension of the Ajax link type, but modified to
 * provide flag count.
 *
 * @ActionLinkType(
 *   id = "count_link",
 *   label = @Translation("Joinup Count link node entity"),
 *   description = "An AJAX action link which displays the count with the flag."
 * )
 */
class CountLink extends OriginalCountLink {

  /**
   * Get the action link formatted for use in entity links.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The flaggable entity.
   * @param string $view_mode
   *   The flaggable entity view mode.
   *
   * @return array
   *   The render array.
   */
  public function getAsFlagLink(FlagInterface $flag, EntityInterface $entity, $view_mode): array {
    assert(is_subclass_of($this, ActionLinkTypePluginInterface::class), __TRAIT__ . ' is intended to be used in action link plugin.');

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
