<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Exception;

/**
 * Thrown by providers when one or more fetch operations fail.
 *
 * Wraps HostingPlatformFetchException to add provider context.
 */
class ProviderFetchException extends HostingPlatformFetchException {}
