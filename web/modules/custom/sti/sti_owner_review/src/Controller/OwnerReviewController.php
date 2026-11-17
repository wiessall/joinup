<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\OwnerQueue;
use Drupal\sti_owner_review\ProposalApplier;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists owners with a pending AI proposal, per collection.
 */
final class OwnerReviewController extends ControllerBase {

  public function __construct(private readonly OwnerQueue $queue) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('sti_owner_review.queue'));
  }

  public function collections(): array {
    $queue = $this->queue->byCollection();
    $rows = [];
    foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple(array_keys($queue)) as $collection) {
      $rows[] = [
        $collection->label(),
        count($queue[$collection->id()]),
        ['data' => $this->link($this->t('Review'), 'sti_owner_review.collection', ['collection' => $collection->id()])],
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Collection'), $this->t('Owners to review'), $this->t('Operations')],
      '#rows' => $rows,
      '#empty' => $this->t('No AI proposals are waiting. Proposals arrive with <code>owner_api.py push</code>.'),
      '#cache' => ['max-age' => 0],
    ];
  }

  public function collectionTitle(NodeInterface $collection): string {
    return (string) $this->t('Owner review: @collection', ['@collection' => $collection->label()]);
  }

  public function collection(NodeInterface $collection): array {
    $ids = $this->queue->byCollection((int) $collection->id())[$collection->id()] ?? [];
    $rows = [];
    foreach ($this->entityTypeManager()->getStorage('node')->loadMultiple($ids) as $owner) {
      $proposal = ProposalApplier::proposal($owner);
      $rows[] = [
        ['data' => $owner->toLink()->toRenderable()],
        implode(', ', array_keys($proposal['fields'])),
        $proposal['source_type'] ?? '',
        ['data' => $this->link($this->t('Review'), 'sti_owner_review.review', ['collection' => $collection->id(), 'owner' => $owner->id()])],
      ];
    }
    return [
      '#type' => 'table',
      '#header' => [$this->t('Owner'), $this->t('Proposed'), $this->t('Source'), $this->t('Operations')],
      '#rows' => $rows,
      '#empty' => $this->t('No owner used by this collection has a pending AI proposal.'),
      '#cache' => ['max-age' => 0],
    ];
  }

  private function link($title, string $route, array $parameters): array {
    return [
      '#type' => 'link',
      '#title' => $title,
      '#url' => Url::fromRoute($route, $parameters),
      '#attributes' => ['class' => ['btn', 'btn-outline-primary', 'btn-sm']],
    ];
  }

}
