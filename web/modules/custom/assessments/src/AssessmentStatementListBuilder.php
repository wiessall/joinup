<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;

/**
 * Provides a list controller for the assessment statement entity type.
 */
final class AssessmentStatementListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('Name');
    $header['bundle'] = $this->t('Type');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\assessments\AssessmentInterface $entity */
    $row['id'] = $entity->toLink();
    $row['bundle'] = $entity->bundle();
    return $row + parent::buildRow($entity);
  }

}
