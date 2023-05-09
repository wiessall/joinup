<?php

namespace Drupal\joinup_like\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\flag\FlagInterface;
use Drupal\flag_count\Plugin\ActionLink\CountLink;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Provides the Count link type for rdf entities.
 *
 * This class is an extension of the Ajax link type, but modified to
 * provide flag count.
 *
 * @ActionLinkType(
 *   id = "count_link_rdf",
 *   label = @Translation("Count link Rdf entity"),
 *   description = "An example AJAX action link which displays the count with
 *   the flag."
 * )
 */
class RdfCountLink extends CountLink {

  /**
   * {@inheritdoc}
   */
  public function getUrl($action, FlagInterface $flag, EntityInterface $entity): Url {
    return match ($action) {
      'flag' => Url::fromRoute('joinup_like.rdf.action_link_flag', [
        'flag' => $flag->id(),
        'entity_id' => UriEncoder::encodeUrl($entity->id()),
      ]),
      default => Url::fromRoute('joinup_like.rdf.action_link_unflag', [
        'flag' => $flag->id(),
        'entity_id' => UriEncoder::encodeUrl($entity->id()),
      ]),
    };
  }

}
