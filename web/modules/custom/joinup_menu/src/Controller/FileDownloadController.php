<?php

declare(strict_types = 1);

namespace Drupal\joinup_menu\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\file\FileInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Returns responses for the file download route.
 */
final class FileDownloadController extends ControllerBase {

  /**
   * Serves a file download.
   */
  public function downloadFile(string $file_identifier): array {
    $file = $this->processFileIdentifier($file_identifier);
    $response = new BinaryFileResponse($file->getFileUri());
    $response->setContentDisposition('attachment', $file->getFilename());
    return $response->send();
  }

  /**
   * Processes the file identifier.
   *
   * The file identifier is in the form of {file_id}-{file_name}. The reason is
   * to avoid easy spamming of the download link. The file name is not used
   * here, but it could be used to check if the file name matches the file id.
   * It is URL encoded, so it needs to be decoded.
   *
   * @param string $file_identifier
   *   The file identifier.
   *
   * @return \Drupal\file\FileInterface|null
   *   The file object or NULL if the file does not exist.
   */
  protected function processFileIdentifier(string $file_identifier): ?FileInterface {
    preg_match('/^(\d+)-(.*)$/', $file_identifier, $matches);
    $fileId = $matches[1];
    $fileName = urldecode($matches[2]);
    $file = $this->entityTypeManager()->getStorage('file')->load($fileId);
    if (!$file) {
      return NULL;
    }

    // Additionally check if the file name matches the file id.
    if ($file->getFilename() !== $fileName) {
      return NULL;
    }

    return $file;
  }

  /**
   * Checks access to the file download.
   */
  public function checkAccess(string $file_identifier): AccessResultInterface {
    return AccessResult::allowedIf((bool) $this->processFileIdentifier($file_identifier));
  }

}
