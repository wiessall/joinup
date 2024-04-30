<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Annotation;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a code hosting platform plugin.
 *
 * @see plugin_api
 *
 * @Annotation
 */
class EuOssCatalogueHostingPlatform extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The label of the plugin.
   *
   * @ingroup plugin_translatable
   */
  public Translation $label;

  /**
   * The code hosting platform base URL.
   *
   * Some plugins have non-configurable base URL (e.g. GitHub). They can provide
   * the base URL in the annotation.
   */
  public ?string $baseUrl = NULL;

  /**
   * API detection weight.
   *
   * The API guesser service calls each code hosting platform plugin in order to
   * determine the platform handling a certain URL, until one of them knows to
   * handle that URL. The order in which the plugins are queried is important.
   * For instance, the `github` and `gitalb_com` plugins are cheap and their
   * detection algorithm is trivial, thus they should be called first.
   *
   * The priority is a signed integer, with a Drupal weight-like logic: a lower
   * integer means a higher priority. This definition entry is optional, and
   * defaults to 0.
   *
   * @see \Drupal\eu_oss_catalogue\ApiGuesser
   */
  public int $weight = 0;

}
