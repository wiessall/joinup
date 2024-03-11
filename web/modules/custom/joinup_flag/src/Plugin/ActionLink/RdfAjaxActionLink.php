<?php

declare(strict_types=1);

namespace Drupal\joinup_flag\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\flag\FlagInterface;
use Drupal\flag\Plugin\ActionLink\AJAXactionLink;

/**
 * Provides the AJAX link type for RDF entities.
 *
 * This class is an extension of the Reload link type, but modified to
 * provide AJAX links.
 *
 * @ActionLinkType(
 *   id = "rdf_ajax_link",
 *   label = @Translation("RDF AJAX link"),
 *   description = @Translation("An RDF AJAX JavaScript request will be made without reloading the page.")
 * )
 */
class RdfAjaxActionLink extends AJAXactionLink {

  use RdfActionLinkTrait;

  /**
   * {@inheritdoc}
   */
  public function getAsFlagLink(FlagInterface $flag, EntityInterface $entity, $view_mode): array {
    $build = parent::getAsFlagLink($flag, $entity, $view_mode);
    $build['#attached']['library'][] = 'flag/flag.link_ajax';
    $build['#attributes']['class'][] = 'use-ajax';

    return $build;
  }

}
