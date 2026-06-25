<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Block;

use Drupal\collection\Entity\CodeOfConductMembershipInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
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
    'user' => new EntityContextDefinition('entity:user', new TranslatableMarkup('User Context')),
  ],
)]
class CollectionCoCMessageBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    $user = $this->getContextValue('user');
    $group = $this->getContextValue('og');
    return AccessResult::allowedIf($user instanceof AccountInterface && $user->isAuthenticated() && $group instanceof GroupInterface);
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
    if (!$collection->isClosed() || $collection->isArchived()) {
      return $build;
    }

    // Inject a lazy_builder to render CoC messages.
    $build['messages'] = [
      '#lazy_builder' => [static::class . '::renderCocMessages', [$collection->id()]],
      '#create_placeholder' => TRUE,
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // Ignore cache contexts from context_definition, we need to vary per
    // og_group_context, and user.permissions (part of required_cache_contexts).
    return ['og_group_context', 'og_role', 'user.permissions'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    // Ignore cache tags from context_definition, we only need the one from
    // collection (added in ::build).
    return [];
  }

  /**
   * Lazy builder callback; renders the CoC messages (e.g.: new CoC to accept).
   *
   * @param string $collection_id
   *   The collection ID.
   *
   * @return array
   *   A renderable array containing the CoC messages.
   */
  #[TrustedCallback]
  public static function renderCocMessages(string $collection_id): array {
    $build = [];
    $collection = \Drupal::entityTypeManager()->getStorage('node')->load($collection_id);
    if (!$collection instanceof CollectionInterface || !$collection->hasCodeOfConductToAccept()) {
      return $build;
    }

    $currentUser = \Drupal::currentUser();

    // Get the active membership for the current user, if any.
    $membership = $collection->getMembership();
    if (!$membership instanceof CodeOfConductMembershipInterface || $membership->isLastDocumentRevisionAccepted()) {
      return $build;
    }

    // If the membership has a bypass for the CoC, do not show the message.
    if ($currentUser->hasPermission('bypass code of conduct') || $membership->bypassesCodeOfConduct()) {
      return $build;
    }

    $entity_repository = \Drupal::service(EntityRepositoryInterface::class);
    $collection = $entity_repository->getTranslationFromContext($collection);
    $coc = $entity_repository->getTranslationFromContext($collection->getCodeOfConduct());

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          t('We updated our <a href=":coc_url" target="_blank">Code of Conduct</a> to ensure safe and respectful environment for all members of %collection.<br> You can accept or decline it <a href=":coc_form_url" target="_blank">here</a>.', [
            '%collection' => $collection->label(),
            ':coc_url' => $coc->toUrl()->toString(),
            ':coc_form_url' => Url::fromRoute('collection.code_of_conduct_consent', ['node' => $collection->id()])->toString(),
          ]),
        ],
      ],
      '#status_headings' => [
        'warning' => t('Important information'),
      ],
    ];
  }

}
