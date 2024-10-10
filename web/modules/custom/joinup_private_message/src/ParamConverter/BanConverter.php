<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\ParamConverter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageBanInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts {{private_message_ban}} path param to PrivateMessageBan entity.
 */
class BanConverter extends EntityConverter {

  /**
   * Constructs a new BanConverter.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, $entity_repository, protected AccountProxyInterface $current_user) {
    parent::__construct($entity_type_manager, $entity_repository);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return parent::applies($definition, $name, $route) && !empty($definition['type']) && $definition['type'] === 'entity:private_message_ban';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (!$ban = parent::convert($value, $definition, $name, $defaults)) {
      return NULL;
    }
    assert($ban instanceof PrivateMessageBanInterface);

    if ($ban->getOwnerId() != $this->current_user->id() || $ban->getTargetId() == $this->current_user->id()) {
      return NULL;
    }

    return $ban;
  }

}
