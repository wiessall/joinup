<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\interoperable_europe\InteroperableEuropeAcademyInterface;

/**
 * Entity reference selection plugin for public solutions in IOPEU Academy.
 */
#[EntityReferenceSelection(
  id: 'joinup_badge',
  label: new TranslatableMarkup('IOPEU Academy solutions'),
  group: 'joinup_badge',
  weight: 1,
  entity_types: ['node']
)]
class IopeuAcademySolution extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return [
      'target_type' => 'node',
      'target_bundles' => ['solution'],
      'sort' => ['field' => '_none'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $iopeuAcademyIdQuery = current(
      $nodeStorage->getQuery()->accessCheck()
        ->condition('uuid', InteroperableEuropeAcademyInterface::GROUP_UUID)
        ->execute()
    );

    $query = parent::buildEntityQuery($match, $match_operator)
      ->condition('og_audience', $iopeuAcademyIdQuery)
      ->condition('status', 1)
      ->condition('field_state', ['published', 'archived'], 'IN');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

}
