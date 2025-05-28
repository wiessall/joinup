<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument;

use Drupal\views\Attribute\ViewsArgument;
use Drupal\views\Plugin\views\argument\StringArgument;

/**
 * Wraps the "string" argument handler in order to decode the RDF entity ID.
 *
 * @todo Remove this in ISAICP-8573.
 */
#[ViewsArgument("og_audience_group")]
class StringGroupArgument extends StringArgument {

  /**
   * {@inheritdoc}
   */
  public function setArgument($arg): bool {
    return $arg ? parent::setArgument($arg) : FALSE;
  }

}
