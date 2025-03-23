<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Interface for collection entities in Joinup.
 */
interface CollectionInterface extends GroupInterface {

  /**
   * The role for full members of a collection.
   */
  const string OG_ROLE_FULL_MEMBER = 'node-collection-full_member';

  /**
   * Returns the solutions that are affiliated with this collection.
   *
   * @param bool $onlyPublished
   *   (optional) Whether to return only published solutions. Defaults to FALSE.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface[]
   *   The solutions.
   */
  public function getSolutions(bool $onlyPublished = FALSE): array;

  /**
   * Returns the IDs of the solutions that are affiliated with this collection.
   *
   * @param bool $onlyPublished
   *   (optional) Whether to return only published solutions. Defaults to FALSE.
   *
   * @return string[]
   *   The solutions.
   */
  public function getSolutionIds(bool $onlyPublished = FALSE): array;

  /**
   * Returns the collection glossary settings.
   *
   * @return array
   *   The collection glossary settings.
   *
   * @throws \Exception
   *   When the collection misses a 'collection_settings' meta entity.
   */
  public function getGlossarySettings(): array;

  /**
   * Whether the collection is closed.
   *
   * In a closed collection users can only become members after being approved
   * by a facilitator. The user memberships will be initially in pending state.
   *
   * @return bool
   *   TRUE if the collection is closed, FALSE if it is open.
   */
  public function isClosed(): bool;

  /**
   * Whether there is any Code of Conduct what new members must accept.
   *
   * @return bool
   *   TRUE if the collection is closed and there is also a published
   *   Code of Conduct, FALSE otherwise.
   */
  public function hasCodeOfConductToAccept(): bool;

  /**
   * Blocks the full membership of all users on change of the Code of Conduct.
   *
   * This method is called when the Code of Conduct is added or changed. It
   * uses direct queries to block the full membership of all users in the
   * collection. The direct queries are used for performance purposes. It also
   * clears the cache of the collection.
   */
  public function revokeFullMemberships(): void;

  /**
   * Restores the full membership of all users in the collection.
   *
   * This method is called when the Code of Conduct is removed or the
   * collection is set to closed. It uses direct queries to restore the full
   * membership of all users in the collection. The direct queries are used for
   * performance purposes. It also clears the cache of the collection.
   */
  public function restoreFullMemberships(): void;

  /**
   * Invalidates the memberships of all users and the collection itself.
   *
   * @param array $membershipIds
   *   The membership IDs to invalidate.
   */
  public function invalidateMembershipsAndSelf(array $membershipIds): void;

  /**
   * Returns the Code of Conduct, if any.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The Code of Conduct if it is set, NULL otherwise.
   */
  public function getCodeOfConduct(): ?ContentEntityInterface;

}
