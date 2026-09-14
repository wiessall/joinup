<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Plugin\NodeRevisionDelete;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;
use Drupal\node_revision_delete\Attribute\NodeRevisionDelete;
use Drupal\node_revision_delete\Plugin\NodeRevisionDelete\Amount;

/**
 * Whether to delete a solution revision based on the revision amount.
 *
 * Extends the default revision amount plugin to protect solution revisions
 * referred to by releases via the `solution_data` field.
 */
#[NodeRevisionDelete(
  id: 'solution_revision_amount',
  label: new TranslatableMarkup('Delete solution revisions when a certain amount is reached but preserves those referred to by releases.'),
)]
final class SolutionRevisionAmount extends Amount {

  /**
   * {@inheritdoc}
   */
  public function getRevisionsToDelete(QueryInterface $query, int $active_vid, NodeInterface $node): array {
    assert($node instanceof SolutionInterface);

    if ($referredRevisions = $this->getRevisionsReferredByReleases($node)) {
      // Exclude solution revisions referred to by releases.
      $query->condition('vid', $referredRevisions, 'NOT IN');
    }

    return parent::getRevisionsToDelete($query, $active_vid, $node);
  }

  /**
   * {@inheritdoc}
   *
   * This method is not called in the current site configuration because only
   * the `amount` plugin is used for solutions. However, we want to make sure
   * that solution revisions referred to by releases are protected in the case
   * a new plugin, such as `created`, will be added for solutions.
   */
  public function getRevisionsToProtect(QueryInterface $query, int $active_vid, NodeInterface $node): array {
    assert($node instanceof SolutionInterface);

    $vids = parent::getRevisionsToProtect($query, $active_vid, $node);
    $protectRevisions = $this->getRevisionsReferredByReleases($node);

    return array_unique([...$vids, ...$protectRevisions]);
  }

  /**
   * Returns the revision IDs of a given solution, referred by releases.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $solution
   *   The solution.
   *
   * @return list<int>
   *   List of revision IDs.
   */
  protected function getRevisionsReferredByReleases(SolutionInterface $solution): array {
    $vids = $this->entityTypeManager->getStorage('node')->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'release')
      ->condition('solution_data.target_id', $solution->id())
      ->groupBy('solution_data.target_revision_id')
      ->execute();
    return array_map(intval(...), array_column($vids, 'solution_data_target_revision_id'));
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['amount']['#description'] = $this->t('Applies only to solutions. Revisions referred to by releases are never deleted and are not counted towards this amount, so the number of preserved revisions might exceed it.');

    return $form;
  }

}
