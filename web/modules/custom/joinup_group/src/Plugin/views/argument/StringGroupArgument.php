<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument;

use Drupal\views\Plugin\views\argument\StringArgument;

/**
 * Wraps the "string" argument handler in order to decode the RDF entity ID.
 *
 * @ingroup views_argument_handlers
 *
 * @todo Replace this with 'string' argument in every view in ISAICP-8572.
 *
 * @ViewsArgument("og_audience_group")
 */
class StringGroupArgument extends StringArgument {

  /**
   * {@inheritdoc}
   */
  public function setArgument($arg): bool {
    return $arg
      ? parent::setArgument($arg)
      : FALSE;
  }

}
