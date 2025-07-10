<?php

declare(strict_types=1);

namespace Drupal\joinup_eulogin\PathProcessor;

use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Adds random parameters to CAS login URLs during URL generation.
 *
 * Add a random query parameter to the login link to force the browser to
 * recalculate the 302 redirect to the external service.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-9903.
 * @todo Remove this processor in ISAICP-9990.
 */
class CasRandomParameterProcessor implements OutboundPathProcessorInterface {

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    if (in_array($path, ['/cas', '/caslogin', '/eulogin'], TRUE)) {
      $options['query'] = $options['query'] ?? [];
      $options['query']['t'] = bin2hex(random_bytes(6));
    }

    return $path;
  }

}
