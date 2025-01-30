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
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\collection\Entity\CodeOfConductMembershipInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;

/**
 * Displays message to the user when the Code of Conduct requires new consent.
 */
#[Block(
  id: 'collection_coc_message',
  admin_label: new TranslatableMarkup('Collection CoC message'),
  context_definitions: [
    'og' => new ContextDefinition('entity', new TranslatableMarkup('Organic group')),
    'user' => new EntityContextDefinition('entity:user', new TranslatableMarkup('User Context')),
  ],
)]
class CollectionCoCMessageBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    return AccessResult::allowedIf(
      $this->getContextValue('user')->isAuthenticated()
      && $this->getContextValue('og') instanceof GroupInterface
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $build = [];
    $cacheability = new CacheableMetadata();
    $user = $this->getContextValue('user');
    $group = $this->getContextValue('og');

    // Ensure we have a collection from the OG context.
    $collection = $group instanceof SolutionInterface ? $group->getCollection() : $group;
    if (!$collection instanceof CollectionInterface) {
      return $build;
    }

    // Ensure the collection has a CoC.
    $cacheability->addCacheableDependency($collection)->applyTo($build);
    $coc = $collection->getCodeOfConduct();
    if (!$coc instanceof NodeInterface) {
      return $build;
    }

    // Ensure the user is a member of the collection.
    $cacheability->addCacheableDependency($coc)->applyTo($build);
    $membership = $collection->getMembership((int) $user->id());
    if (!$membership instanceof CodeOfConductMembershipInterface) {
      return $build;
    }

    // Ensure the user has to accept a new CoC.
    $cacheability->addCacheableDependency($membership)->applyTo($build);
    if (!$collection->hasCodeOfConductToAccept() || $membership->isLastDocumentRevisionAccepted()) {
      return $build;
    }

    // Render the message.
    $build['message'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('We updated our <a href=":coc_url" target="_blank">Code of Conduct</a> to ensure safe and respectful environment for all members of %collection.<br> You can accept or decline it <a href=":coc_form_url" target="_blank">here</a>.', [
            '%collection' => $collection->label(),
            ':coc_url' => $coc->toUrl()->toString(),
            ':coc_form_url' => Url::fromRoute('collection.code_of_conduct_consent', ['node' => $collection->id()])->toString(),
          ]),
        ],
      ],
      '#status_headings' => [
        'warning' => $this->t('Important information'),
      ],
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    $contexts = parent::getCacheContexts();

    // Unset route coming from the "og" context_definition, we only need to vary
    // this per user and og_group_context (enforce those). Having "user" ensures
    // the block will be auto-placeholdered (see auto_placeholder_conditions)
    // in order to reduce the impact on the main dynamic page cache.
    unset($contexts['route']);
    return Cache::mergeContexts($contexts, ['user', 'og_group_context']);
  }

}
