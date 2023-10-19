<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Core\Url;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;

/**
 * Plugin implementation of the 'link' formatter.
 *
 * @FieldFormatter(
 *   id = "auto_blank_link",
 *   label = @Translation("Auto blank link"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class AutoBlankLinkFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  protected function buildUrl(LinkItemInterface $item): Url {
    $url = parent::buildUrl($item);
    if ($url->isExternal()) {
      $options = $url->getOptions();

      $options['attributes']['target'] = '_blank';
      $options['attributes']['rel'] = 'noopener';

      $url->setOptions($options);
    }

    return $url;
  }

}
