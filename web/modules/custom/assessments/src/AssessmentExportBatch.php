<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\PublicStream;

/**
 * Batch process for export reports pdf as zip archive.
 */
final class AssessmentExportBatch {

  private const string EXPORT_PATH = 'assessments/pdf';

  public const string FILE_NAME = 'assessments.zip';

  /**
   * Generates a PDF for a single assessment report.
   *
   * @param int $assessmentId
   *   Assesment report ID.
   * @param array $context
   *   Batch context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function generatePdf(int $assessmentId, array &$context): void {
    self::prepareExportDirectory();

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
      $fileSystem = \Drupal::service('file_system');
      $directory = PublicStream::basePath() . AssessmentExportBatch::EXPORT_PATH;
      foreach ($fileSystem->scanDirectory($directory, '/\.pdf$/i') as $file) {
        $fileSystem->delete($file->uri);
      }

      \Drupal::messenger()
        ->addStatus(t('Assessment ZIP package has been created.'));
    }
    catch (\Throwable $e) {
      \Drupal::messenger()->addError(t('Unable to create the ZIP archive.'));
      \Drupal::logger('assessment_export')->error($e->getMessage());
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
    self::prepareExportDirectory();
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
   * Prepares directory for reports files and archive.
   */
  private static function prepareExportDirectory(): void {
    $fileSystem = \Drupal::service('file_system');
    $destination = static::getExportLocation();
    $fileSystem->prepareDirectory(
      $destination,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    );
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

}
