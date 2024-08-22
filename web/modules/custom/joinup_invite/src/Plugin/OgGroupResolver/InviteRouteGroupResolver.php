<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Plugin\OgGroupResolver;

use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_invite\Entity\InvitationInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;

/**
 * Resolves a group from the invite route.
 *
 * @OgGroupResolver(
 *   id = "group_from_invite",
 *   label = "Group from Invite",
 *   description = @Translation("Resolves group from the invite."),
 * )
 */
class InviteRouteGroupResolver extends RouteGroupResolver {

  /**
   * {@inheritdoc}
   */
  protected function getContentEntityPaths(): array {
    return [
      '/invitation/{invitation}/{action}/{hash}' => 'invitation',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    $invite = $this->getContentEntity();
    if (!$invite instanceof InvitationInterface) {
      return;
    }

    $entity = $invite->getEntity();
    if (!$entity instanceof GroupInterface) {
      return;
    }

    $collection->addGroup($entity, ['route']);
    $this->stopPropagation();
  }

}
