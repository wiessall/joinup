<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\Utility\Error;

/**
 * Batch process for export reports pdf as zip archive.
 */
final class AssessmentExportBatch {

  private const string EXPORT_PATH = 'assessments/pdf';

  public const string FILE_NAME = 'assessments.zip';

  /**
   * Prepares directory for reports files and archive.
   */
  public static function prepareExportDirectory(): void {
    self::cleanupFiles();

    $directory = AssessmentExportBatch::getExportLocation();
    \Drupal::service('file_system')->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    );
  }

  /**
   * Generates a PDF for a single assessment report.
   *
   * @param int $assessmentId
   *   Assesment report ID.
   * @param array $context
   *   Batch context.
   */
  public static function generatePdf(int $assessmentId, array &$context): void {
    $report = \Drupal::entityTypeManager()
      ->getStorage('assessment')
      ->load($assessmentId);

    if (!$report) {
      return;
    }
    $printBuilder = \Drupal::service('entity_print.print_builder');
    $printEngine = \Drupal::service('plugin.manager.entity_print.print_engine')
      ->createSelectedInstance('pdf');

    $filename = str_replace(['/', '\\'], '', $report->label()) . '.pdf';
    $destination = self::EXPORT_PATH . '/' . $filename;

    $printBuilder->savePrintable(
      [$report],
      $printEngine,
      'public',
      $destination,
    );

    $context['results'][] = self::getExportLocation($filename);
  }

  /**
   * Creates the ZIP archive containing generated PDFs.
   *
   * @param bool $success
   *   Batch success.
   * @param array $results
   *   Batch results.
   */
  public static function finished(bool $success, array $results): void {
    if (!$success) {
      \Drupal::messenger()->addError(t('The export failed.'));
      return;
    }

    try {
      self::createZip($results);
      self::cleanupFiles(TRUE);
      \Drupal::messenger()->addStatus(t('Assessment ZIP package has been created.'));
    }
    catch (\Throwable $e) {
      \Drupal::messenger()->addError(t('Unable to create the ZIP archive.'));
      Error::logException(\Drupal::logger('assessments'), $e);
    }
  }

  /**
   * Creates a ZIP archive from the exported files.
   *
   * @param string[] $files
   *   File URIs.
   */
  private static function createZip(array $files): void {
    $fileSystem = \Drupal::service('file_system');

    $zipPath = self::getExportLocation(AssessmentExportBatch::FILE_NAME);
    $zip = new \ZipArchive();

    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== TRUE) {
      throw new \RuntimeException(sprintf('Unable to open ZIP archive "%s".', $zipPath));
    }

    foreach ($files as $uri) {
      $path = $fileSystem->realpath($uri);

      if (!$path || !is_file($path)) {
        continue;
      }

      $zip->addFile($path, basename($path));
    }
  }

  /**
   * Returns the location of the files.
   *
   * @param string|null $fileName
   *   (optional) If omitted the directory is returned.
   *
   * @return string
   *   A dorectory or a file path.
   */
  public static function getExportLocation(?string $fileName = NULL): string {
    $location = PublicStream::basePath() . '/' . self::EXPORT_PATH;
    if ($fileName) {
      $location .= "/$fileName";
    }
    return $location;
  }

  /**
   * Removes generated files.
   *
   * @param bool $pdfOnly
   *   (optional) TRUE to remove only .pdf files. If omitted, all files are
   *   removed.
   */
  private static function cleanupFiles(bool $pdfOnly = FALSE): void {
    $fileSystem = \Drupal::service('file_system');
    $directory = self::getExportLocation();
    if (is_dir($directory)) {
      $pattern = $pdfOnly ? '/\.pdf$/i' : '/.*/';
      foreach ($fileSystem->scanDirectory($directory, $pattern) as $file) {
        $fileSystem->delete($file->uri);
      }
    }
  }

}
