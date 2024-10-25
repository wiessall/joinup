<?php

declare(strict_types=1);

namespace Drupal\joinup_flag\Plugin\search_api\processor;

use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;

/**
 * Adds entity type ID and bundle id of the flagging.
 *
 * @SearchApiProcessor(
 *   id = "joinup_entity_bundle",
 *   label = @Translation("Portal entity bundle"),
 *   description = @Translation("Get entity bundle from flagging item."),
 *   stages = {
 *     "add_properties" = 0,
 *   },
 *   locked = true,
 *   hidden = true,
 * )
 */
class JoinupEntityBundle extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL): array {
    $properties = [];

    if (!$datasource) {
      $definition = [
        'label' => $this->t('Entity bundle'),
        'description' => $this->t("The indexed item's entity type ID and bundle"),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
      ];
      $properties['search_api_entity_bundle'] = new ProcessorProperty($definition);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item): void {
    try {
      $flagging = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }
    if (!($item->getDatasourceId() === 'entity:flagging')) {
      return;
    }

    $entity = $flagging->getFlaggable();
    $fields = $item->getFields(FALSE);
    $fields = $this->getFieldsHelper()
      ->filterForPropertyPath($fields, NULL, 'search_api_entity_bundle');
    foreach ($fields as $field) {
      $field->addValue(implode(':', [
        $entity->getEntityTypeId(),
        $entity->bundle(),
      ]));
    }
  }

}
