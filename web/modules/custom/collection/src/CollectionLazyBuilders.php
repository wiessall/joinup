<?php

declare(strict_types=1);

namespace Drupal\collection;

use Drupal\collection\Form\JoinCollectionForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines a service for #lazy_builder callbacks for the Collection module.
 */
class CollectionLazyBuilders {

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

}
