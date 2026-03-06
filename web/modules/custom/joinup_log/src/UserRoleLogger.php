<?php

declare(strict_types=1);

namespace Drupal\joinup_log;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * User role logger.
 */
readonly class UserRoleLogger implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'logger.channel.group_membership')]
    protected LoggerChannelInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
  ) {}

  /**
   * Detects role changes.
   *
   * @param \Drupal\user\UserInterface $user
   *   Updated user.
   */
  public function detectChanges(UserInterface $user): void {
    $newRoles = $user->getRoles();

    $originalRoles = $user->getOriginal()?->getRoles() ?? [];

    $newDiff = $this->removeSystemRoles(array_diff($newRoles, $originalRoles));
    $removedDiff = $this->removeSystemRoles(array_diff($originalRoles, $newRoles));

    $this->log($newDiff, 'joinup:add_role', $user);
    $this->log($removedDiff, 'joinup:remove_role', $user);
  }

  /**
   * Removes system roles.
   *
   * @param array $roles
   *   Machine names.
   *
   * @return array
   *   Cleaned roles.
   */
  protected function removeSystemRoles(array $roles): array {
    return array_diff($roles, [
      AccountInterface::ANONYMOUS_ROLE,
      AccountInterface::AUTHENTICATED_ROLE,
    ]);
  }

  /**
   * Transforms machine names into labels.
   *
   * @param array $roleMachineNames
   *   Machine names.
   *
   * @return array
   *   Labels.
   */
  protected function transformToRoleLabels(array $roleMachineNames): array {
    $roleStorage = $this->entityTypeManager->getStorage('user_role');
    $transformed = [];

    foreach ($roleMachineNames as $roleMachineName) {
      $role = $roleStorage->load($roleMachineName);
      if (!$role) {
        continue;
      }

      $transformed[] = $role->label();
    }

    return $transformed;
  }

  /**
   * Logs changes.
   *
   * @param array $roleMachineNames
   *   Changed roles.
   * @param string $type
   *   Log type.
   * @param \Drupal\user\UserInterface $user
   *   Updated user.
   */
  protected function log(array $roleMachineNames, string $type, UserInterface $user): void {
    $roleLabels = $this->transformToRoleLabels($roleMachineNames);

    foreach ($roleLabels as $roleLabel) {
      $message = match ($type) {
        'joinup:add_role' => 'Successfully added the @role role to @actor by @user.',
        'joinup:remove_role' => 'Successfully removed the @role role for the @actor by @user.',
      };

      $this->logger->info($message, [
        '@actor' => $user->getDisplayName(),
        '@role' => $roleLabel,
        '@user' => $this->currentUser->getDisplayName(),
        'type' => $type,
        'actor' => $user->id(),
      ]);
    }
  }

}
