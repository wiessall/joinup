<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines a class to build a listing of meta entity type entities.
 *
 * @see \Drupal\rdf_meta_entity\Entity\RdfMetaEntityType
 */
class RdfMetaEntityTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['title'] = $this->t('Label');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    $row['title'] = [
      'data' => $entity->label(),
      'class' => ['menu-label'],
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    $build = parent::render();
    $build['table']['#empty'] = $this->t('No meta entity types available.');
    return $build;
  }

}
