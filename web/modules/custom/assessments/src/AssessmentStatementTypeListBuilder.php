<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines a class to build a listing of assessment statement type entities.
 *
 * @see \Drupal\assessments\Entity\AssessmentStatementType
 */
final class AssessmentStatementTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();

    $build['table']['#empty'] = $this->t(
      'No assessment statement types available. <a href=":link">Add assessment statement type</a>.',
      [':link' => Url::fromRoute('entity.assessment_statement_type.add_form')->toString()],
    );

    return $build;
  }

}
