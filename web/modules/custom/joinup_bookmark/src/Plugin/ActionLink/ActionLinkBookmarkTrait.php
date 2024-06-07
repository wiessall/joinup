<?php

declare(strict_types=1);

namespace Drupal\joinup_bookmark\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\Plugin\ActionLink\AJAXactionLink;

/**
 * Action link functionality for bookmark.
 */
trait ActionLinkBookmarkTrait {

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
    assert(is_subclass_of($this, AJAXactionLink::class), __TRAIT__ . ' is intended to be used in action link plugin.');

    $build = parent::getAsFlagLink($flag, $entity, $view_mode);
    if (isset($build['#theme'])) {
      return $build;
    }

    $accessView = $flag->actionAccess('view', $this->currentUser, $entity);
    if ($accessView->isAllowed()) {
      return [
        '#theme' => 'flag_bookmark_text',
        '#flag' => $flag,
        '#flaggable' => $entity,
        '#view_mode' => $view_mode,
        '#cache' => [
          'tags' => ['flagging_list:' . $flag->id()],
        ],
      ];
    }

    return $build;
  }

}
