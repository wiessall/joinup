<?php

declare(strict_types=1);

namespace Drupal\joinup_security\Plugin\TrackUsage\Track;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file_url\FileUrlHandler;
use Drupal\file_url\Plugin\Field\FieldType\FileUrlItem;
use Drupal\track_usage\Attribute\Track;
use Drupal\track_usage\Plugin\TrackUsage\TrackPluginBase;

/**
 * Track File URL fields.
 *
 * @see https://www.drupal.org/project/file_url
 */
#[Track(
  id: 'file_url',
  label: new TranslatableMarkup('File URL'),
  fieldTypes: ['file_url'],
)]
class FileUrl extends TrackPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function getItemTargetEntities(FieldItemInterface $item): iterable {
    assert($item instanceof FileUrlItem);
    $targetId = $item->getValue()['target_id'] ?? NULL;
    if ($targetId && ($file = FileUrlHandler::urlToFile($targetId)) && !FileUrlHandler::isRemote($file)) {
      yield $file;
    }
  }

}
