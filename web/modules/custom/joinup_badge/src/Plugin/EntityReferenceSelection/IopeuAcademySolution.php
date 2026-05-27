<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

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

  protected const string IOPEU_ACADEMY_COLLECTION_UUID = '1cc1b2c4-f65a-49cb-9a31-d5a49f283ea3';

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
        ->condition('uuid', static::IOPEU_ACADEMY_COLLECTION_UUID)
        ->execute()
    );

    $query = parent::buildEntityQuery($match, $match_operator)
      ->condition('og_audience', $iopeuAcademyIdQuery);

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    return $form;
  }

}
