<?php

declare(strict_types=1);

namespace Drupal\contact_form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;

/**
 * Resolves the node referenced by a contact form "Page URL" value.
 */
final class ContactFormUrlResolver {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Determines the entity referenced by a contact form message's Page URL.
   *
   * @param \Drupal\Core\Entity\EntityInterface $message
   *   The contact form message entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The referenced entity, or null if the Page URL is empty or does not
   *   resolve to one.
   */
  public function getEntityFromUrl(EntityInterface $message): ?EntityInterface {
    $uri = $message->get('field_contact_url')->first()?->uri ?? '';
    if ($uri === '') {
      return NULL;
    }

    try {
      $url = Url::fromUri($uri);
    }
    catch (\Exception $e) {
      return NULL;
    }

    if (!$url->isRouted()) {
      return NULL;
    }

    $route_parameters = $url->getRouteParameters();
    $nid = $route_parameters['node'] ?? NULL;
    if (!is_numeric($nid)) {
      return NULL;
    }

    if ($url->getRouteName() !== 'entity.node.canonical') {
      return NULL;
    }

    return $this->entityTypeManager->getStorage('node')->load($nid);
  }

}
