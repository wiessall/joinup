<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Exception;

/**
 * Thrown when a code hosting platform client is missing a token.
 */
class MissingAccessTokenException extends EuOssCatalogueException {}
