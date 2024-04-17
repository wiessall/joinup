<?php

declare(strict_types=1);

namespace Drupal\joinup_flag\Plugin\ActionLink;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;
use Drupal\flag\ActionLink\ActionLinkTypePluginInterface;
use Drupal\flag\FlagInterface;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Rdf action link functionality.
 */
trait RdfActionLinkTrait {

  /**
   * Gets URL.
   *
   * @param string $action
   *   Action type.
   * @param \Drupal\flag\FlagInterface $flag
   *   Flag entity.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Flagged entity.
   * @param string $view_mode
   *   View mode.
   *
   * @return \Drupal\Core\Url
   *   Flag action url.
   */
  public function getUrl($action, FlagInterface $flag, EntityInterface $entity, string $view_mode = 'default'): Url {
    assert(is_subclass_of($this, ActionLinkTypePluginInterface::class), __TRAIT__ . ' is intended to be used in action link plugin.');

    return match ($action) {
      'flag' => Url::fromRoute('joinup_flag.rdf.action_flag', [
        'flag' => $flag->id(),
        'entity_id' => UriEncoder::encodeUrl($entity->id()),
        'view_mode' => $view_mode,
      ]),
      default => Url::fromRoute('joinup_flag.rdf.action_unflag', [
        'flag' => $flag->id(),
        'entity_id' => UriEncoder::encodeUrl($entity->id()),
        'view_mode' => $view_mode,
      ]),
    };
  }

}
