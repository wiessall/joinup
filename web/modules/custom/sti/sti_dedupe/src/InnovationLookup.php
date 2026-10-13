<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Finds the ATIO innovations derived from given solutions.
 *
 * Replaced innovations are excluded: they are unpublished history, and a
 * solution that was on one now counts as being on its survivor.
 */
final class InnovationLookup {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ReplacementStore $replacements,
  ) {}

  /**
   * Returns live innovations derived from any of the given solutions.
   *
   * @param int[] $solutionNids
   *   Solution node ids.
   *
   * @return int[]
   *   Innovation node ids, sorted ascending.
   */
  public function derivedFrom(array $solutionNids): array {
    if (!$solutionNids || !$this->isAvailable()) {
      return [];
    }
    $ids = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ContentModel::INNOVATION)
      ->condition(ContentModel::DERIVED_FROM . '.target_id', array_values($solutionNids), 'IN')
      ->execute();
    $ids = array_map('intval', array_values($ids));
    $live = array_values(array_diff($ids, $this->replacements->replacedAmong($ids)));
    sort($live);
    return $live;
  }

  /**
   * Whether the atio_innovation bundle and its derived-from field exist.
   *
   * The field belongs to the atio module; without it there is nothing to
   * link to, and callers record the decision and the solution claims only.
   */
  public function isAvailable(): bool {
    $definitions = $this->entityFieldManager->getFieldDefinitions('node', ContentModel::INNOVATION);
    return isset($definitions[ContentModel::DERIVED_FROM]);
  }

}
