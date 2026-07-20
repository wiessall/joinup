<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\assessments\AssessmentExportBatch;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to export all reports PDF files in zip archive.
 */
class AssessmentExportForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected DateFormatterInterface $dateFormatter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'assessment_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $zipPath = AssessmentExportBatch::getExportLocation(AssessmentExportBatch::FILE_NAME);
    if ($zipPath && file_exists($zipPath)) {
      $creationTime = filectime($zipPath);
      $form['existing'] = [
        '#type' => 'link',
        '#title' => $this->t('Download @file', ['@file' => AssessmentExportBatch::FILE_NAME]),
        '#url' => $this->fileUrlGenerator->generate($zipPath),
        '#prefix' => '<div>',
        '#suffix' => ' - ' . match(TRUE) {
          $creationTime === FALSE => $this->t('cannot determine the file creation time'),
          default => $this->dateFormatter->format($creationTime, 'custom', 'Y-m-d H:i'),
        } . '</div>',
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate ZIP'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('assessment');

    $ids = $storage->getQuery()
      ->condition('type', 'report')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    $operations[] = [[AssessmentExportBatch::class, 'prepareExportDirectory']];
    foreach ($ids as $id) {
      $operations[] = [
        [AssessmentExportBatch::class, 'generatePdf'],
        [$id],
      ];
    }

    batch_set([
      'title' => $this->t('Generating assessment PDFs'),
      'operations' => $operations,
      'finished' => [AssessmentExportBatch::class, 'finished'],
    ]);
  }

}
