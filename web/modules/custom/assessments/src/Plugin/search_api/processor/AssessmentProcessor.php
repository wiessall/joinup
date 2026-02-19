<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\FieldInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles assessment entities.
 */
#[SearchApiProcessor(
  id: 'assessment_processor',
  label: new TranslatableMarkup('Assessment processor'),
  description: new TranslatableMarkup('Handle assessment entities.'),
  stages: [
    'alter_items' => 0,
    'preprocess_index' => 0,
  ],
)]
class AssessmentProcessor extends ProcessorPluginBase {

  /**
   * The entity repository.
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->entityRepository = $container->get('entity.repository');
    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index): bool {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'assessment') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items): void {
    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()?->getValue();
      if ($object instanceof AssessmentInterface && !$object->isPublished()) {
        unset($items[$item_id]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items): void {
    foreach ($items as $item) {
      $entity = $item->getOriginalObject()?->getValue();
      if (!$entity instanceof AssessmentInterface) {
        continue;
      }

      // Index the "EU" country term when the field value is empty.
      $field = $item->getField('assessment_org_country');
      \assert($field instanceof FieldInterface);
      if (!$field->getValues()) {
        $eu_term = $this->entityRepository->loadEntityByUuid('taxonomy_term', AssessmentInterface::EU_COUNTRY_TERM);
        if ($eu_term instanceof TermInterface) {
          $field->addValue($eu_term->id());
        }
      }
    }
  }

}
