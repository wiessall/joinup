<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\QueueWorker;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\imagecache_external\ImagecacheExternal;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Downloads the queued remote files.
 */
#[QueueWorker(
  id: 'joinup_oss_catalogue_imagecache_warmer',
  title: new TranslatableMarkup('Joinup OSS Catalogue remote image cache warmer'),
  cron: ['time' => 60],
)]
class JoinupOssCatalogueImagecacheWarmerQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    $plugin_definition,
    protected readonly StateInterface $state,
    protected readonly FileSystemInterface $fileSystem,
    protected readonly ConfigFactory $configFactory,
    protected readonly ModuleHandlerInterface $moduleHandler,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(StateInterface::class),
      $container->get(FileSystemInterface::class),
      $container->get('config.factory'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    ['source' => $source] = $data;
    $this->wipeImagecacheExternalItem($source);
    imagecache_external_generate_path($source);
  }

  /**
   * Removes cached asset or unavailable flag of Imagecache External item.
   *
   * @param string|null $url
   *   The URL of the item.
   */
  protected function wipeImagecacheExternalItem(?string $url): void {
    if (ImagecacheExternal::isUnavailableUrl($url)) {
      $this->state->set(ImagecacheExternal::MISSING_RESOURCES_KEY, array_diff_key(
        $this->state->get(ImagecacheExternal::MISSING_RESOURCES_KEY),
        [$url => $url]
      ));
    }

    $cachePath = $this->getCachePathFromUrl($url, $isLocal);
    if ($isLocal || !$cachePath) {
      return;
    }

    if (file_exists($cachePath)) {
      $this->fileSystem->delete($cachePath);
    }
    image_path_flush($cachePath);
  }

  /**
   * Generates the path to the locally stored, cached image.
   *
   * Extracted from the actual (patched) state of Imagecache External module.
   *
   * @param string|null $url
   *   The URL to the remote image.
   * @param mixed $isLocal
   *   Optional variable altered by reference. It is changed to TRUE if the URL
   *   is considered local, and to FALSE otherwise.
   *
   * @return string|false
   *   The URI to the locally cached image asset. FALSE if the URL is empty, or
   *   NULL if the local cache directory cannot be prepared.
   */
  protected function getCachePathFromUrl(?string $url, mixed &$isLocal = NULL): string|false {
    $isLocal = FALSE;
    if (empty($url) || ImagecacheExternal::isUnavailableUrl($url)) {
      return FALSE;
    }
    // Get configuration.
    $imageCacheConfig = $this->configFactory->get('imagecache_external.settings');

    // Create the external images directory and ensure it's writable.
    $hash = md5($url);
    $filename = $hash;

    // Default scheme, directory and optional subdirectories.
    $defaultScheme = $this->configFactory->get('system.file')->get('default_scheme');
    $imagecacheExternalDirectory = $imageCacheConfig->get('imagecache_directory');
    if ($imageCacheConfig->get('imagecache_subdirectories')) {
      $imagecacheExternalDirectory .= '/' . $hash[0] . '/' . $hash[1];
    }

    // Allow altering the scheme and directory.
    $alter = ['scheme' => &$defaultScheme, 'directory' => &$imagecacheExternalDirectory];
    $context = ['url' => $url, 'hash' => $hash];
    $this->moduleHandler->alter('imagecache_external_destination', $alter, $context);

    // Check if this is a non-standard file stream and adjust accordingly.
    $scheme = StreamWrapperManager::getScheme($url);
    if ($scheme == $defaultScheme) {
      // Don't try fetch already existing files on local file system.
      $isLocal = TRUE;
      return $url;
    }
    elseif ($scheme != 'http' && $scheme != 'https') {
      // Obtain the "real" URL to this file.
      $url = $this->fileSystem->realpath($url);
    }

    // Parse the url to get the path components.
    $urlParameters = UrlHelper::parse($url);

    // Add the extension for real images.
    $extension = strtolower(pathinfo($urlParameters['path'], PATHINFO_EXTENSION));
    if (in_array($extension, ['jpg', 'png', 'gif', 'jpeg', 'webp', 'svg'])) {
      $filename .= '.' . $extension;
    }
    else {
      // Use a default extension.
      $filename .= $imageCacheConfig->get('imagecache_default_extension');
    }

    $directory = $defaultScheme . '://' . $imagecacheExternalDirectory;
    $cacheDirPrepared = $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    return $cacheDirPrepared ? "$directory/$filename" : FALSE;
  }

}
