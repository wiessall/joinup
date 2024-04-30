<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\og\OgGroupAudienceHelperInterface;

/**
 * Reusable methods for node group content.
 */
trait GroupContentTrait {

  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function hasGroup(): bool {
    try {
      $this->getGroup();
    }
    catch (MissingGroupException $e) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): GroupInterface {
    $group = $this->getFirstReferencedEntity($this->getGroupFieldName());
    if (empty($group) || !$group instanceof GroupInterface) {
      throw new MissingGroupException();
    }

    return $group;
  }

  /**
   * {@inheritdoc}
   */
  public function setGroup(GroupInterface $group): self {
    $this->set($this->getGroupFieldName(), $group->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupId(): string {
    $ids = $this->getReferencedEntityIds($this->getGroupFieldName());
    if (empty($ids['rdf_entity'])) {
      throw new MissingGroupException();
    }
    return array_shift($ids['rdf_entity']);
  }

  /**
   * {@inheritdoc}
   */
  public function getSharedInField(): ?EntityReferenceFieldItemList {
    return NULL;
  }

  /**
   * Returns the group reference field name.
   *
   * @return string
   *   The group reference field name.
   */
  protected function getGroupFieldName(): string {
    return OgGroupAudienceHelperInterface::DEFAULT_FIELD;
  }

}
