<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\ParamConverter;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\ParamConverter\EntityConverter;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\private_message\Entity\PrivateMessageBanInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts {{private_message_ban}} path param to PrivateMessageBan entity.
 */
class BanConverter extends EntityConverter {

  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityRepositoryInterface $entityRepository, protected AccountProxyInterface $currentUser) {
    parent::__construct($entityTypeManager, $entityRepository);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
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

    if ($ban->getOwnerId() !== $this->currentUser->id() || $ban->getTargetId() === $this->currentUser->id()) {
      return NULL;
    }

    return $ban;
  }

}
