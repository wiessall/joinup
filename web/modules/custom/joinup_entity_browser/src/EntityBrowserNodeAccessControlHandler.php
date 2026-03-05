<?php

declare(strict_types=1);

namespace Drupal\joinup_entity_browser;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Extends the node access control handler for entity browser create access.
 *
 * @see \Drupal\joinup_entity_browser\Routing\EntityBrowserRouteSubscriber
 */
class EntityBrowserNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    if ($entity_bundle !== 'solution' || !$this->requestStack->getCurrentRequest()?->attributes->get('_entity_browser_create_access')) {
      return parent::checkCreateAccess($account, $context, $entity_bundle);
    }

    return AccessResult::allowed();
  }

}
