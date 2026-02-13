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
    $header['id'] = $this->t('Identifier');
    $header['bundle'] = $this->t('Type');
    $header['judgement'] = $this->t('Judgement');
    $header['interpretation'] = $this->t('Interpretation');
    return $header;
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\assessments\AssessmentInterface $entity */
    $row['id'] = $entity->id();
    $row['bundle'] = $entity->bundle();
    $row['judgement'] = $entity->get('judgement')->value;
    $row['interpretation'] = $entity->get('interpretation')->value;
    return $row;
  }

}
