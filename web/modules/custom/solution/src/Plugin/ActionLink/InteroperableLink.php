<?php

declare(strict_types=1);

namespace Drupal\solution\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\Plugin\ActionLink\AJAXactionLink;

/**
 * Provides the AJAX link type.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "interoperable_link",
 *   label = @Translation("Interoperable link"),
 *   description = @Translation("An AJAX JavaScript request will be made without reloading the page.").
 * )
 */
class InteroperableLink extends AJAXactionLink {

  /**
   * View modes where actions are allowed.
   *
   * @var string[]
   */
  protected array $actionViewModes = ['group_header'];

  /**
   * {@inheritdoc}
   */
  public function getAsFlagLink(FlagInterface $flag, EntityInterface $entity, $view_mode): array {
    $build = parent::getAsFlagLink($flag, $entity, $view_mode);
    if (isset($build['#theme']) && in_array($view_mode, $this->actionViewModes, TRUE)) {
      return $build;
    }

    $accessView = $flag->actionAccess('view', $this->currentUser, $entity);
    if ($accessView->isAllowed()) {
      return [
        '#theme' => 'flag_interoperable_text',
        '#flagged' => $flag->isFlagged($entity),
        '#view_mode' => $view_mode,
        '#cache' => [
          'tags' => ['flagging_list:' . $flag->id()],
        ],
      ];
    }

    return $build;
  }

}
