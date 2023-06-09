<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\layout_paragraphs\Access\LayoutParagraphsBuilderAccess;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\og\OgAccessInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;

/**
 * Layout paragraphs builder access decorator.
 *
 * Layout paragraph is not aware about our custom permissions for
 * RDF/organic group. This decorator resolves that by calling
 * userAccessGroupContentEntityOperation function on group entity.
 */
class LayoutParagraphsBuilderAccessDecorator implements AccessInterface {

  /**
   * Constructs decorator.
   *
   * @param \Drupal\layout_paragraphs\Access\LayoutParagraphsBuilderAccess $decorated
   *   Decorated service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\og\OgAccessInterface $ogAccess
   *   Organic access service.
   */
  public function __construct(
    protected LayoutParagraphsBuilderAccess $decorated,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected OgAccessInterface $ogAccess,
  ) {
  }

  /**
   * It checks access.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs layout object.
   * @param string|null $operation
   *   The operation being performed (i.e. 'create' or 'delete').
   * @param string|null $component_uuid
   *   The specific component being acted on.
   * @param \Drupal\paragraphs\ParagraphsTypeInterface|null $paragraph_type
   *   The paragraph type.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   */
  public function access(
    AccountInterface $account,
    LayoutParagraphsLayout $layout_paragraphs_layout,
    ?string $operation = NULL,
    ?string $component_uuid = NULL,
    ?ParagraphsTypeInterface $paragraph_type = NULL
  ): AccessResult {
    $entity = $layout_paragraphs_layout->getEntity();

    // Use default approach in case it is not a group entity.
    if (!$entity instanceof GroupContentInterface || !$entity->hasGroup()) {
      return $this->decorated->access($account, $layout_paragraphs_layout, $operation, $component_uuid, $paragraph_type);
    }

    $entityGroup = $entity->getGroup();

    $access = $layout_paragraphs_layout->getParagraphsReferenceField()
      ->access('edit', $account, TRUE);

    // It checks permission on the group entity instead of on content entity.
    $lp_operation = $entity->isNew() ? 'create' : 'update';
    $access = $access->andIf($this->ogAccess->userAccessGroupContentEntityOperation($lp_operation, $entityGroup, $entity, $account));

    // The rest of code is copy/paste from LayoutParagraphsBuilderAccess.
    if ($component_uuid) {
      $paragraph = $layout_paragraphs_layout
        ->getComponentByUuid($component_uuid)
        ->getEntity();
      $access = $access->andIf($paragraph->access($operation, $account, TRUE));
    }

    if ($paragraph_type) {
      $access_handler = $this->entityTypeManager->getAccessControlHandler('paragraph');
      $access = $access->andIf($access_handler->createAccess($paragraph_type->id(), $account, [], TRUE));
    }

    return $access;
  }

}
