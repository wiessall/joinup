<?php

declare(strict_types=1);

namespace Drupal\collection;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\collection\Entity\CodeOfConductMembershipInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Form\JoinCollectionForm;

/**
 * Defines a service for #lazy_builder callbacks for the Collection module.
 */
class CollectionLazyBuilders {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly FormBuilderInterface $formBuilder,
    protected readonly AccountInterface $account,
  ) {}

  /**
   * Lazy builder callback; builds the Join Collection form.
   *
   * @param string $collection_id
   *   The collection ID.
   *
   * @return array
   *   A renderable array containing the comment form.
   */
  #[TrustedCallback]
  public function renderJoinCollectionForm(string $collection_id): array {
    $collection = $this->entityTypeManager->getStorage('node')->load($collection_id);
    return $this->formBuilder->getForm(JoinCollectionForm::class, $this->account, $collection);
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
  public function renderCocMessages(string $collection_id): array {
    $build = [];
    $collection = $this->entityTypeManager->getStorage('node')->load($collection_id);
    if (!$collection instanceof CollectionInterface || !$collection->hasCodeOfConductToAccept()) {
      return $build;
    }

    $membership = $collection->getMembership((int) $this->account->id());
    if (!$membership instanceof CodeOfConductMembershipInterface || $membership->isLastDocumentRevisionAccepted()) {
      return $build;
    }

    return [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('We updated our <a href=":coc_url" target="_blank">Code of Conduct</a> to ensure safe and respectful environment for all members of %collection.<br> You can accept or decline it <a href=":coc_form_url" target="_blank">here</a>.', [
            '%collection' => $collection->label(),
            ':coc_url' => $collection->getCodeOfConduct()->toUrl()->toString(),
            ':coc_form_url' => Url::fromRoute('collection.code_of_conduct_consent', ['node' => $collection->id()])->toString(),
          ]),
        ],
      ],
      '#status_headings' => [
        'warning' => $this->t('Important information'),
      ],
    ];
  }

}
