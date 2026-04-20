<?php

declare(strict_types=1);

namespace Drupal\joinup_bookmark\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\Plugin\ActionLink\AJAXactionLink;

/**
 * Provides the AJAX link type for node entities.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "bookmark_link",
 *   label = @Translation("Node bookmark link"),
 *   description = @Translation("A Node AJAX JavaScript request will be made without reloading the page.")
 * )
 */
class NodeBookmarkLink extends AJAXactionLink {

  /**
   * {@inheritdoc}
   */
  public function getAsFlagLink(FlagInterface $flag, EntityInterface $entity, ?string $view_mode = NULL): array {
    assert(is_subclass_of($this, AJAXactionLink::class), __CLASS__ . ' is intended to be used in action link plugin.');

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
