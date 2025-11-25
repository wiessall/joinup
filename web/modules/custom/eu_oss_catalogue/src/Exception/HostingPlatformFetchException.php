<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Exception;

/**
 * Thrown when fetching from hosting platforms or endpoints fails.
 *
 * Triggers catalogue sync skip (no additions, updates, or cleanup).
 *
 * @see \Drupal\eu_oss_catalogue\Exception\ProviderFetchException
 */
class HostingPlatformFetchException extends EuOssCatalogueException {}
