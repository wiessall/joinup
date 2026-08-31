<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\When;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\tmgmt\JobItemInterface;
use Drupal\tmgmt_ec_etranslation\Plugin\tmgmt\Translator\ETranslationTranslator;

/**
 * Behat step definitions for testing the TMGMT translation review workflow.
 */
class TmgmtContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * The IDs of the translation jobs created during the scenario.
   *
   * @var int[]
   */
  protected array $jobIds = [];

  /**
   * Places a translation job for a node in the "Needs review" state.
   *
   * @param string $title
   *   The title of the source node.
   * @param string $bundle
   *   An alias of a bundle as defined in the
   *   \Drupal\joinup\Traits\NameMappingsTrait::bundleAliases method.
   * @param string $langcode
   *   The target language code (e.g. "bg").
   */
  #[Given('a translation job in review to :langcode for the :title :bundle')]
  public function createReviewJob(string $title, string $bundle, string $langcode): void {
    $node = $this->getEntityByLabel('node', $title, $this->translateBundle($bundle));

    $job = tmgmt_job_create('en', $langcode, 0, [
      'label' => "Test job: {$node->label()} > $langcode",
      'translator' => ETranslationTranslator::ID,
    ]);
    $job->save();

    $this->jobIds[] = $job->id();

    $item = tmgmt_job_item_create('content', 'node', $node->id(), ['tjid' => $job->id()]);
    $item->save();

    $data_service = \Drupal::service('tmgmt.data');
    foreach ($data_service->filterTranslatable($item->getData()) as $key => $value) {
      $item->updateData($key, [
        '#translation' => ['#text' => $value['#text']],
        '#status' => TMGMT_DATA_ITEM_STATE_REVIEWED,
      ]);
    }
    $item->setState(JobItemInterface::STATE_REVIEW);
    $item->save();
  }

  /**
   * Makes a distribution's parent reference point to a non-existent node.
   *
   * @param string $title
   *   The distribution title.
   */
  #[Given('the release referenced by the :title distribution no longer exists')]
  public function danglingReleaseReference(string $title): void {
    /** @var \Drupal\node\NodeInterface $distribution */
    $distribution = $this->getEntityByLabel('node', $title, 'distribution');
    $database = \Drupal::database();

    $missing_nid = (int) $database->query('SELECT MAX([nid]) FROM {node}')->fetchField() + 1;

    foreach (['node__release', 'node_revision__release'] as $table) {
      $database->merge($table)
        ->keys([
          'entity_id' => $distribution->id(),
          'revision_id' => $distribution->getRevisionId(),
          'langcode' => $distribution->language()->getId(),
          'delta' => 0,
          'deleted' => 0,
        ])
        ->fields([
          'bundle' => 'distribution',
          'release_target_id' => $missing_nid,
        ])
        ->execute();
    }

    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$distribution->id()]);
  }

  /**
   * Visits the translation review form of a node's job item in review.
   *
   * @param string $title
   *   The title of the source node.
   * @param string $bundle
   *   An alias of a bundle as defined in the
   *   \Drupal\joinup\Traits\NameMappingsTrait::bundleAliases method.
   *
   * @throws \RuntimeException
   *   Thrown when the node has no job item in the "Needs review" state.
   */
  #[When('I visit the translation review form of the :title :bundle')]
  public function visitReviewForm(string $title, string $bundle): void {
    $node = $this->getEntityByLabel('node', $title, $this->translateBundle($bundle));
    $items = \Drupal::entityTypeManager()->getStorage('tmgmt_job_item')->loadByProperties([
      'plugin' => 'content',
      'item_type' => 'node',
      'item_id' => (string) $node->id(),
      'state' => JobItemInterface::STATE_REVIEW,
    ]);
    if (!$items) {
      throw new \RuntimeException("No job item in review found for {$node->label()}.");
    }
    $this->visitPath(reset($items)->toUrl()->toString());
  }

  /**
   * Removes the translation jobs created during the scenario.
   */
  #[AfterScenario]
  public function cleanTranslationJobs(): void {
    if (!$this->jobIds) {
      return;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('tmgmt_job');
    if ($jobs = $storage->loadMultiple($this->jobIds)) {
      $storage->delete($jobs);
    }
    $this->jobIds = [];
  }

}
