<?php

declare(strict_types=1);

namespace Drupal\joinup_bundle_class;

use Drupal\Core\Url;
use Drupal\file\FileInterface;

/**
 * Reusable methods for entities that have a logo field.
 */
trait LogoTrait {

  /**
   * {@inheritdoc}
   */
  public function getLogoAsRenderArray($display_options = []): array {
    return $this->get($this->getLogoFieldName())->view($display_options);
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoAsFile(): ?FileInterface {
    assert(method_exists($this, 'getMainPropertyValue'), __TRAIT__ . ' depends on JoinupBundleClassFieldAccessTrait. Please include it in your class.');
    $value = $this->getFirstReferencedEntity($this->getLogoFieldName());
    return $value instanceof FileInterface ? $value : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoAsUrl(array $options = []): ?Url {
    if (!$file = $this->getLogoAsFile()) {
      return NULL;
    }
    /** @var \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator */
    $file_url_generator = \Drupal::service('file_url_generator');
    return $file_url_generator->generate($file->getFileUri())->setOptions($options);
  }

}
