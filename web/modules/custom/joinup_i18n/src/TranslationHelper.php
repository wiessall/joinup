<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\tmgmt\Data;
use Drupal\tmgmt\SourceManager;
use Drupal\tmgmt_content\Plugin\tmgmt\Source\ContentEntitySource;

/**
 * Provides translation helper methods.
 */
class TranslationHelper {

  public function __construct(
    protected readonly SourceManager $sourceManager,
    protected readonly Data $dataHelper,
  ) {}

  /**
   * Checks whether an entity has translatable fields.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return bool
   *   Whether an entity has translatable fields.
   */
  public function hasTranslatableFields(ContentEntityInterface $entity): bool {
    $plugin = $this->sourceManager->createInstance('content');
    assert($plugin instanceof ContentEntitySource);
    $data = $plugin->extractTranslatableData($entity);
    return !empty($this->dataHelper->filterTranslatable($data));
  }

}
