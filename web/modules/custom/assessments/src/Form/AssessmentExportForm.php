<?php

declare(strict_types=1);

namespace Drupal\assessments\Form;

use Drupal\assessments\AssessmentExportBatch;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to export all reports pdf files in zip archive.
 */
class AssessmentExportForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('file_url_generator'),
    );
  }

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
    $zipUri = AssessmentExportBatch::getZipUri();
    if ($zipUri && file_exists($zipUri)) {
      $form['existing'] = [
        '#type' => 'link',
        '#title' => $this->t('Download assessments.zip'),
        '#url' => $this->fileUrlGenerator->generate($zipUri),
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
    $directory = PublicStream::basePath() . AssessmentExportBatch::EXPORT_PATH;

    if (is_dir($directory)) {
      foreach ($this->fileSystem->scanDirectory($directory, '/.*/') as $file) {
        $this->fileSystem->delete($file->uri);
      }
    }

    $storage = $this->entityTypeManager->getStorage('assessment');

    $ids = $storage->getQuery()
      ->condition('type', 'report')
      ->condition('status', 1)
      ->accessCheck(FALSE)
      ->execute();

    $operations = [];
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
