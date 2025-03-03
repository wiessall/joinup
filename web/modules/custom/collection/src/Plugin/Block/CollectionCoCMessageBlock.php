<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;

/**
 * Displays message to the user when the Code of Conduct requires new consent.
 */
#[Block(
  id: 'collection_coc_message',
  admin_label: new TranslatableMarkup('Collection CoC message'),
  context_definitions: [
    'og' => new ContextDefinition('entity', new TranslatableMarkup('Organic group')),
  ],
)]
class CollectionCoCMessageBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf($this->getContextValue('og') instanceof GroupInterface);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];
    $group = $this->getContextValue('og');

    // Ensure we have a collection from the OG context.
    $collection = $group instanceof SolutionInterface ? $group->getCollection() : $group;
    if (!$collection instanceof CollectionInterface) {
      return $build;
    }

    // Add collection cacheability and ensure the collection is closed.
    CacheableMetadata::createFromObject($collection)->applyTo($build);
    if (!$collection->isClosed()) {
      return $build;
    }

    // Inject a lazy_builder to render CoC messages.
    $build['messages'] = [
      '#lazy_builder' => [
        'joinup_collection.lazy_builders:renderCocMessages',
        [$collection->id()],
      ],
      '#create_placeholder' => TRUE,
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();

    // Unset route coming from the "og" context_definition, we only need to vary
    // this per og_group_context.
    unset($contexts['route']);
    return Cache::mergeContexts($contexts, ['og_group_context']);
  }

}
