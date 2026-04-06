<?php

declare(strict_types=1);

namespace Drupal\joinup_reports;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\csv_serialization\Encoder\CsvEncoder;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a form base to generate and export data in a CSV.
 */
abstract class ExportBatchedDataFormBase extends FormBase {

  use AutowireTrait;

  public function __construct(
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cacheStorage,
    protected DateFormatterInterface $dateFormatter,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['help'] = [
      '#markup' => $this->t('Press generate data to generate the list.'),
    ];

    $form['actions'] = [
      '#type' => 'container',
    ];

    if ($data = $this->cacheStorage->get($this->getCid())) {
      $form['help']['#markup'] = $this->t('Last dump was created at :date and will be removed by :expire', [
        ':date' => $this->dateFormatter->format((int) $data->created),
        ':expire' => $this->dateFormatter->format((int) $data->expire),
      ]);

      $form['actions']['download_link'] = [
        '#type' => 'submit',
        '#value' => $this->t('Download'),
        '#submit' => ['::downloadCsv'],
      ];

      $form_state->set('download_data', $data->data);
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t(':generate data', [
        ':generate' => empty($data) ? 'Generate' : 'Regenerate',
      ]),
      '#submit' => ['::generateData'],
    ];

    return $form;
  }

  /**
   * Downloads the CSV file of the user list.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function downloadCsv(array &$form, FormStateInterface $form_state): void {
    $headers = [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => "attachment;filename={$this->getDownloadedFileName()}",
    ];

    $response = new Response($form_state->get('download_data'), 200, $headers);
    $form_state->setResponse($response);
  }

  /**
   * Stores the data into the cache.
   *
   * This method should be called as a finished callback for the batch process.
   *
   * @param bool $success
   *   Whether the batch ended without a fatal error.
   * @param array $results
   *   The result set.
   * @param array $operations
   *   The remaining operations.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A redirect response to the page that serves the CSV file as a download.
   */
  public static function storeToCache(bool $success, array $results, array $operations): Response {
    // Set the expiration time to 1 day.
    $expire = \Drupal::time()->getRequestTime() + 3600 * 24;

    // Use ';' as a delimiter as some software have an issue when the delimiter
    // and the decimal separator is the same symbol.
    $csv_encoder = new CsvEncoder(';');
    $data = $csv_encoder->encode(array_values($results['data']), 'csv');
    \Drupal::cache()->set(static::getCid(), $data, $expire);
    \Drupal::messenger()->addMessage('Data has been rebuilt.');

    // Handle the file here.
    return new RedirectResponse(Url::fromRoute(static::getRedirectRouteName())
      ->toString());
  }

  /**
   * Generates the data array.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  abstract protected function generateData(array &$form, FormStateInterface $form_state): void;

  /**
   * Returns the cache ID.
   *
   * @return string
   *   The cache ID.
   */
  abstract protected static function getCid(): string;

  /**
   * Returns the route name for the report download.
   *
   * @return string
   *   The route name.
   */
  abstract protected static function getRedirectRouteName(): string;

  /**
   * Returns the name of the file that will be downloaded.
   *
   * @return string
   *   The file name.
   */
  protected function getDownloadedFileName(): string {
    return 'data_export.csv';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
