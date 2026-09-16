<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;

/**
 * Hooks for the release solution revision reference.
 *
 * Each release has a reference to the parent solution revision which was active
 * at the time of the release creation. Target solution revisions should be
 * protected against deletion.
 */
final class ReleaseSolutionDataHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Implements hook_node_access().
   */
  #[Hook('node_access')]
  public function solutionRevisionDeleteAccess(NodeInterface $solution, string $operation, AccountInterface $account): AccessResultInterface {
    if (!($solution instanceof SolutionInterface) || $operation !== 'delete revision') {
      return AccessResult::neutral();
    }

    if (!$releases = $this->getReferringReleases($solution)) {
      return AccessResult::neutral();
    }

    $cacheMetadata = new CacheableMetadata();
    foreach ($releases as $release) {
      $cacheMetadata->addCacheableDependency($release);
    }

    return AccessResult::forbidden('Solution revision referred by release(s)')
      ->addCacheableDependency($cacheMetadata);
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_revision_overview_form_alter')]
  public function formAlter(array &$form, FormStateInterface $formState): void {
    $entity = $formState->getBuildInfo()['args'][0];
    if (!$entity instanceof SolutionInterface) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $pager = $this->configFactory->get('diff.settings')
      ->get('general_settings.revision_pager_limit');

    $vids = array_keys(
      $storage->getQuery()->accessCheck()
        ->allRevisions()
        ->condition('langcode', $entity->language()->getId())
        ->condition('revision_translation_affected', '1')
        ->condition('nid', $entity->id())
        ->sort('vid', 'DESC')
        ->pager($pager)
        ->execute()
    );

    foreach (Element::children($form['node_revisions_table']) as $delta => $key) {
      $row =& $form['node_revisions_table'][$key];

      $vid = $vids[$delta];
      $revision = $storage->loadRevision($vid);

      if ($releases = $this->getReferringReleases($revision)) {
        $labels = [];
        foreach ($releases as $release) {
          assert($release instanceof ReleaseInterface);
          $labels[] = $release->label();
        }

        $message = $row['revision']['#context']['message']['#markup'];
        $message .= ' <em>' . $this->t('Revision referred by release(s): @labels', [
          '@labels' => implode(', ', $labels),
        ]) . '</em>';
        $row['revision']['#context']['message']['#markup'] = ltrim($message);
      }
    }
  }

  /**
   * Returns the releases that refer to the given solution revision.
   *
   * @param \Drupal\joinup_solution\Entity\SolutionInterface $solution
   *   The solution revision.
   *
   * @return array<int|string, \Drupal\joinup_release\Entity\ReleaseInterface>
   *   List of releases.
   */
  protected function getReferringReleases(SolutionInterface $solution): array {
    return $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'release',
      'solution_data.target_revision_id' => $solution->getRevisionId(),
    ]);
  }

}
