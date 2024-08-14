<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Entity;

use Drupal\joinup_core\Controller\IdRedirect;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;

/**
 * Reusable code for classes implementing PermalinkInterface interface.
 *
 * @see \Drupal\joinup_core\Entity\PermalinkInterface
 */
trait PermalinkTrait {

  /**
   * {@inheritdoc}
   */
  public function getPermalink(): ?string {
    // Entity ID is pointing to the publication office redirect.
    $namespaces = implode('|', array_keys(IdRedirect::getEntityTypeFromPersistentUriNamespace()));
    $uri = $this instanceof RdfSyncEntityInterface ? $this->getUri() : $this->id();
    if (preg_match('{^http://data.europa.eu/(?:' . $namespaces . ')/}', $uri)) {
      return $uri;
    }

    try {
      // Don't process the path to avoid pathauto aliasing.
      return $this->toUrl('canonical', [
        'path_processing' => FALSE,
        'absolute' => TRUE,
      ])->toString();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
