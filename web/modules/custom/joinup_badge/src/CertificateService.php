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
   *   Certificate owner.
   * @param $status
   *   Certificate status
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUserCertificates(UserInterface $user, $status = NULL): array {
    $properties = [
      'type' => 'certificate',
      'uid' => $user->id(),
    ];
    if ($status) {
      $properties['status'] = $status;
    }
    return $this->entityTypeManager
      ->getStorage('node')
      ->loadByProperties($properties);
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

  /**
   * Update status for user certificates.
   *
   * @param \Drupal\user\UserInterface $user
   *   Certificates owner.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function updateUserCertificatesStatus(UserInterface $user): void {
    $certificates = $this->getUserCertificates($user);
    foreach ($certificates as $certificate) {
      if ($user->isBlocked() && $certificate->status->value == '1') {
        $certificate->setUnpublished();
      }
      elseif ($user->isActive() && $certificate->status->value !== '1') {
        $certificate->setPublished();
      }
      $certificate->save();
    }
  }

}
