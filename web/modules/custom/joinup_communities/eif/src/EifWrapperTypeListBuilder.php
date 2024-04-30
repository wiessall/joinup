<?php

declare(strict_types=1);

namespace Drupal\eif;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * List builder for 'eif_wrapper_type' entities.
 */
class EifWrapperTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    return [
      'type' => $this->t('Type'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    return [
      'type' => $entity->toLink(NULL, 'edit-form'),
    ] + parent::buildRow($entity);
  }

}
