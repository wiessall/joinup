<?php

declare(strict_types=1);

namespace Drupal\assessments\Http;

/**
 * Thrown when a streamed download exceeds the configured maximum.
 */
class MaxDownloadSizeExceededException extends \RuntimeException {}
