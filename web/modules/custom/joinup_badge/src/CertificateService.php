<?php

declare(strict_types=1);

namespace Drupal\joinup_badge;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Service for user certificates.
 */
readonly class CertificateService {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Gets all user certificates.
   *
   * @param \Drupal\user\UserInterface $user
   *   Certificates owner.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUserCertificates(UserInterface $user): array {
    return $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'certificate',
        'uid' => $user->id(),
      ]);
  }

  /**
   * Deletes all user certificates.
   *
   * @param \Drupal\user\UserInterface $user
   *   Certificates owner.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function deleteUserCertificates(UserInterface $user): void {
    $certificates = $this->getUserCertificates($user);
    $this->entityTypeManager->getStorage('node')->delete($certificates);
  }

}
