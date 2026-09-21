<?php

namespace Drupal\import_ird_jsons\Plugin\search_api\processor;

use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\ListDataDefinition;
use Drupal\search_api\Processor\ProcessorPropertyInterface;

/**
 * Multi-valued string property for claim fields.
 * Extends ListDataDefinition so Search API Solr maps it to sm_ (multi)
 * instead of ss_ (single).
 */
class ClaimListProperty extends ListDataDefinition implements ProcessorPropertyInterface {

  protected string $processorId;

  public function __construct(array $values = []) {
    $values['type'] = 'list:string';
    parent::__construct($values);
    $this->processorId = $values['processor_id'] ?? '';
    if (!isset($this->itemDefinition)) {
      $this->setItemDefinition(DataDefinition::create('string'));
    }
  }

  public function getProcessorId(): string {
    return $this->processorId;
  }

  public function isHidden(): bool {
    return FALSE;
  }
}