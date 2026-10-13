<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\Prefixes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Lists pending duplicate reviews and pending claim conflicts.
 */
final class DedupeController extends ControllerBase {

  private const PAGE_SIZE = 25;

  public function __construct(
    private readonly CandidateStore $candidates,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('sti_dedupe.candidates'),
      $container->get('request_stack'),
    );
  }

  /**
   * Lists every collection that has solutions awaiting review.
   */
  public function collections(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'collection')
      ->sort('title')
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $collection) {
      $pending = $this->candidates->countSourcesForCollection((int) $collection->id());
      if ($pending === 0) {
        continue;
      }
      $rows[] = [
        'collection' => $collection->label(),
        'pending' => $pending,
        'operations' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Review'),
            '#url' => Url::fromRoute('sti_dedupe.review', ['collection' => $collection->id()]),
            '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
          ],
        ],
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Collection'),
        $this->t('Need review'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#attributes' => ['class' => ['table', 'table-striped']],
      '#empty' => $this->t('Nothing is waiting for review. Run <code>drush sti-dedupe:scan</code> after an import to populate this list.'),
    ];
  }

  /**
   * Title callback for the per-collection queue.
   */
  public function reviewTitle(NodeInterface $collection): string {
    return $this->t('Import review — @collection', ['@collection' => $collection->label()])->render();
  }

  /**
   * The review queue for one collection.
   */
  public function review(NodeInterface $collection): array {
    $collectionId = (int) $collection->id();
    $total = $this->candidates->countSourcesForCollection($collectionId);

    $page = (int) ($this->requestStack->getCurrentRequest()?->query->get('page') ?? 0);
    $offset = $page * self::PAGE_SIZE;
    $entries = $this->candidates->pageForCollection($collectionId, self::PAGE_SIZE, $offset);

    $storage = $this->entityTypeManager()->getStorage('node');
    $nodeIds = [];
    foreach ($entries as $sourceNid => $entry) {
      $nodeIds[] = $sourceNid;
      $nodeIds[] = $entry['best_nid'];
    }
    $nodes = $nodeIds ? $storage->loadMultiple(array_unique($nodeIds)) : [];

    $rows = [];
    foreach ($entries as $sourceNid => $entry) {
      $source = $nodes[$sourceNid] ?? NULL;
      $best = $nodes[$entry['best_nid']] ?? NULL;
      if (!$source instanceof NodeInterface || !$best instanceof NodeInterface) {
        continue;
      }

      $rows[] = [
        'source' => ['data' => ['#markup' => '<strong>' . $source->label() . '</strong>']],
        'candidate' => $this->t('@title (@collection)', [
          '@title' => $best->label(),
          '@collection' => $this->collectionLabel($best),
        ]),
        'count' => $entry['count'],
        'score' => number_format($entry['best_score'], 2),
        'operations' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Review'),
            '#url' => Url::fromRoute('sti_dedupe.resolve', [
              'collection' => $collectionId,
              'solution' => $sourceNid,
              'candidate' => $best->id(),
            ]),
            '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
          ],
        ],
      ];
    }

    $build['summary'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('@total solutions in this collection have at least one undecided candidate. Decided pairs are hidden and are not offered again.', ['@total' => $total]),
    ];

    $build['table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('New record'),
        $this->t('Best candidate'),
        $this->t('Candidates'),
        $this->t('Score'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#attributes' => ['class' => ['table', 'table-striped']],
      '#empty' => $this->t('No candidates are waiting for review in this collection.'),
    ];

    $build['pager'] = ['#type' => 'pager'];
    $build['#cache']['max-age'] = 0;

    return $build;
  }

  /**
   * Lists claim resolutions waiting for a curator, stale ones first.
   */
  public function conflicts(): array {
    $storage = $this->entityTypeManager()->getStorage('paragraph');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ContentModel::RESOLUTION)
      ->condition(ContentModel::RESOLUTION_STATUS, [ContentModel::STATUS_STALE, ContentModel::STATUS_PENDING], 'IN')
      ->condition('parent_type', 'node')
      ->sort(ContentModel::RESOLUTION_STATUS, 'DESC')
      ->sort('id')
      ->pager(self::PAGE_SIZE)
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $resolution) {
      if (!$resolution instanceof ParagraphInterface) {
        continue;
      }
      $innovation = $resolution->getParentEntity();
      // Only resolutions still attached to an innovation are actionable;
      // detached ones await the paragraphs orphan purger.
      if (!$innovation instanceof NodeInterface || !$this->isAttached($innovation, $resolution)) {
        continue;
      }
      $current = 0;
      foreach ($resolution->get(ContentModel::VERDICTS)->referencedEntities() as $verdict) {
        $current += (int) (bool) $verdict->get(ContentModel::VERDICT_CURRENT)->value;
      }
      $rows[] = [
        'innovation' => ['data' => $innovation->toLink()->toRenderable()],
        'property' => Prefixes::compact((string) $resolution->get(ContentModel::RESOLUTION_PROPERTY)->value),
        'status' => $resolution->get(ContentModel::RESOLUTION_STATUS)->value,
        'values' => $current,
        'operations' => [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Resolve'),
            '#url' => Url::fromRoute('sti_dedupe.conflict', ['paragraph' => $resolution->id()]),
            '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
          ],
        ],
      ];
    }

    return [
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Innovation'),
          $this->t('Property'),
          $this->t('Status'),
          $this->t('Claimed values'),
          $this->t('Operations'),
        ],
        '#rows' => $rows,
        '#attributes' => ['class' => ['table', 'table-striped']],
        '#empty' => $this->t('No claim conflicts are waiting. Conflicts are computed on cron after imports; run <code>drush sti-dedupe:conflicts</code> to compute them now.'),
      ],
      'pager' => ['#type' => 'pager'],
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Whether a resolution is in the innovation's current revision.
   */
  private function isAttached(NodeInterface $innovation, ParagraphInterface $resolution): bool {
    if (!$innovation->hasField(ContentModel::RESOLUTIONS)) {
      return FALSE;
    }
    foreach ($innovation->get(ContentModel::RESOLUTIONS)->getValue() as $item) {
      if ((int) $item['target_id'] === (int) $resolution->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns a solution's collection label, or a dash.
   */
  private function collectionLabel(NodeInterface $solution): string {
    if (!$solution->hasField('og_audience') || $solution->get('og_audience')->isEmpty()) {
      return '—';
    }
    $collection = $solution->get('og_audience')->entity;
    return $collection instanceof NodeInterface ? (string) $collection->label() : '—';
  }

}
