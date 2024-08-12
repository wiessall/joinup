<?php

declare(strict_types=1);

namespace Drupal\solution\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\Plugin\ActionLink\AJAXactionLink;
use Drupal\joinup_flag\Plugin\ActionLink\RdfActionLinkTrait;

/**
 * Provides the AJAX link type for RDF entities.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "rdf_interoperable_link",
 *   label = @Translation("RDF interoperable link"),
 *   description = @Translation("An RDF AJAX JavaScript request will be made
 *   without reloading the page.")
 * )
 */
class RdfInteroperableLink extends AJAXactionLink {

  /**
   * View modes where actions are allowed.
   *
   * @var string[]
   */
  protected array $actionViewModes = ['group_header'];

  use RdfActionLinkTrait;

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
        '#cache' => [
          'tags' => ['flagging_list:' . $flag->id()],
        ],
      ];
    }

    return $build;
  }

}
