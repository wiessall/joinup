<?php

declare(strict_types=1);

namespace Drupal\collection;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\collection\Form\JoinCollectionForm;

/**
 * Defines a service for #lazy_builder callbacks for the Collection module.
 */
class CollectionLazyBuilders implements TrustedCallbackInterface {

  /**
   * Constructs a new CollectionLazyBuilders object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged-in user.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FormBuilderInterface $formBuilder,
    protected AccountInterface $account,
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
  public function renderJoinCollectionForm(string $collection_id): array {
    $collection = $this->entityTypeManager->getStorage('node')->load($collection_id);
    return $this->formBuilder->getForm(JoinCollectionForm::class, $this->account, $collection);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['renderJoinCollectionForm'];
  }

}
