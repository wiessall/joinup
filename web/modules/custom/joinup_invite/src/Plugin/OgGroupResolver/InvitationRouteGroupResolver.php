<?php

declare(strict_types=1);

namespace Drupal\joinup_invite\Plugin\OgGroupResolver;

use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_invite\Entity\InvitationInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;

/**
 * Resolves the collection from the route.
 *
 * @OgGroupResolver(
 *   id = "group_from_invitation",
 *   label = "Group from invitation",
 *   description = @Translation("Sets group context from invitation route."),
 * )
 */
class InvitationRouteGroupResolver extends RouteGroupResolver {

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
    $invitation = $this->getContentEntity();
    if (!$invitation instanceof InvitationInterface) {
      return;
    }

    if (($group = $invitation->getEntity()) && $group instanceof GroupInterface) {
      $collection->addGroup($group, ['route']);
      $this->stopPropagation();
    }
  }

}
