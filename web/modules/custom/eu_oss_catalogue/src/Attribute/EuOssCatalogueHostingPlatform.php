<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Attribute;

use Attribute;
use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;

/**
 * Defines a code hosting platform plugin.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class EuOssCatalogueHostingPlatform extends Plugin {

  /**
   * The plugin ID.
   */
  public string $id;

  /**
   * The label of the plugin.
   *
   * This property is translatable.
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

  /**
   * @param string $id
   * @param \Drupal\Core\Annotation\Translation $label
   * @param string|null $baseUrl
   * @param int $weight
   */
  public function __construct(string $id, Translation $label, ?string $baseUrl, int $weight) {
    $this->id = $id;
    $this->label = $label;
    $this->baseUrl = $baseUrl;
    $this->weight = $weight;
  }

}
