<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\Core\Url;
use Drupal\comment\CommentInterface;
use Drupal\joinup_core\Controller\IdRedirect;
use Drupal\joinup_core\Entity\PermalinkInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Field item list class for the 'permalink' bundle base field.
 *
 * @see joinup_core_entity_bundle_field_info()
 */
class PermalinkFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $entity = $this->getEntity();
    assert($entity instanceof PermalinkInterface);

    if ($entity->isNew()) {
      $this->list[0] = $this->createItem(0, NULL);
      return;
    }

    try {
      if ($entity instanceof NodeInterface) {
        // Entity ID is pointing to the publication office redirect.
        $namespaces = implode('|', array_keys(IdRedirect::getEntityTypeFromPersistentUriNamespace()));
        $uri = $entity instanceof RdfSyncEntityInterface ? $entity->getUri() : $entity->id();
        if ($uri && preg_match('{^http://data.europa.eu/(?:' . $namespaces . ')/}', $uri)) {
          $value = Url::fromUri($uri)->toString();
        }
        else {
          // Don't process the path to avoid pathauto aliasing.
          $value = $entity->toUrl('canonical', [
            'path_processing' => FALSE,
            'absolute' => TRUE,
          ])->toString();
        }
      }
      elseif ($entity instanceof CommentInterface) {
        $value = $entity->permalink()->setOption('absolute', TRUE)->toString();
      }
      else {
        $value = NULL;
      }
    }
    catch (\Exception $e) {
      $value = NULL;
    }

    $this->list[0] = $this->createItem(0, $value);
  }

}
