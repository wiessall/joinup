<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment\Entity;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\NodeCollectionContentTrait;
use Drupal\joinup_core\Entity\PermalinkTrait;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\node\Entity\Node;

/**
 * Entity subclass for the 'assessment_report' bundle.
 */
class AssessmentReport extends Node implements AssessmentReportInterface {

  use NodeCollectionContentTrait;
  use PermalinkTrait;

  /**
   * {@inheritdoc}
   */
  public function getGroup(): CollectionInterface {
    $group = $this->getFirstReferencedEntity('og_audience');
    if (empty($group) || !$group instanceof CollectionInterface) {
      throw new MissingGroupException();
    }
    return $group;
  }

  /**
   * Returns the group reference field name.
   *
   * @return string
   *   The group reference field name.
   */
  protected function getGroupFieldName(): string {
    return 'og_audience';
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupId(): string {
    $ids = $this->getReferencedEntityIds('og_audience');
    if (empty($ids['node'])) {
      throw new MissingGroupException();
    }
    return array_shift($ids['node']);
  }

}
