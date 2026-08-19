<?php

declare (strict_types=1);

namespace Drupal\joinup_core\PathProcessor;

use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\joinup_core\Controller\IdRedirect;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles joinup_core.id_redirect route case when {uuid} param contains a '/'.
 *
 * Because the joinup_core.id_redirect route's {uuid} parameter may contain one
 * or more slashes, we encode the URL parameter as Drupal doesn't allow slashes
 * in parameters, as opposed to Symfony. We revert the parameter encoding,
 * later, in IdRedirect::redirectToRdfEntity().
 *
 * @see https://www.drupal.org/project/drupal/issues/2741939
 * @see https://symfony.com/doc/3.4/routing/slash_in_parameter.html
 * @see \Drupal\joinup_core\Controller\IdRedirect::redirectToRdfEntity()
 */
class IdRedirectUuidProcessor implements InboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processInbound($path, Request $request): string {
    if (strpos($path, '/data/') === 0 && !$request->query->has('uuid')) {
      preg_match('#^/data/(?<namespace>[^/]+)/(?<uuid>.+)$#', $path, $matches);
      if (!empty($matches['namespace']) && !empty($matches['uuid'][0]) && IdRedirect::getEntityTypeFromPersistentUriNamespace($matches['namespace'])) {
        return '/data/' . $matches['namespace'] . '/' . urlencode($matches['uuid']);
      }
    }

    // The EU persistent URI resolver redirects the 'w21' namespace to a path
    // that misses the '/data' prefix, as opposed to the other namespaces, which
    // are correctly resolved to '/data/{namespace}/{uuid}'. Mimic the above
    // chunk to maintain the way it is calculated.
    if (str_starts_with($path, '/w21/') && !$request->query->has('uuid')) {
      return $this->processInbound('/data' . $path, $request);
    }

    return $path;
  }

}
