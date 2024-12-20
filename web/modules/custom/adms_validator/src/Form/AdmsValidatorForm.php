<?php

declare(strict_types=1);

namespace Drupal\adms_validator\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\adms_validator\AdmsValidatorInterface;
use Drupal\file\FileInterface;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Form to validate the ADMS compliance of a RDF or TTL file.
 */
class AdmsValidatorForm extends FormBase {

  public function __construct(
    protected AdmsValidatorInterface $admsValidator,
    protected Session $session,
    protected RdfSyncConnectionInterface $connection,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('adms_validator.validator'),
      $container->get('session'),
      $container->get('rdf_sync.connection'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'adms_validator_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['adms_file'] = [
      '#type' => 'file',
      '#title' => $this->t('File'),
      '#description' => $this->t('An RDF file you want to test for compliance.'),
      '#upload_validators'  => [
        'FileExtension' => ['extensions' => 'rdf ttl'],
      ],
    ];
    $form['info'] = [
      '#markup' => $this->t('This validator uses the <a href="https://github.com/SEMICeu/adms-ap_validator">SEMIC ADMS-AP ruleset</a>.'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Upload'),
      '#button_type' => 'primary',
    ];
    /** @var \Drupal\adms_validator\AdmsValidationResult $validation_errors */
    $validation_errors = $form_state->get('validation_errors');
    if (!empty($validation_errors)) {
      // The form was submitted, and validation errors have been set.
      $form['table'] = $validation_errors->toTable();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Make sure to clear the table.
    $form_state->set('validation_errors', []);
    $form['table'] = [];
    $form_state->setRebuild();

    $file = $this->uploadedFile();
    if (!$file) {
      $form_state->setError($form['adms_file'], 'Please upload a valid RDF file.');
      return;
    }

    try {
      $uri = AdmsValidatorInterface::DEFAULT_VALIDATION_GRAPH . "/{$this->session->getId()}";
      $schema_errors = $this->admsValidator->validateFile($file->getFileUri(), $uri);
    }
    catch (\Exception $e) {
      $form_state->setError($form['adms_file'], $e->getMessage());
      return;
    }

    // Delete the uploaded file from disk.
    $file->delete();
    // Clear the graph.
    $this->connection->update("CLEAR GRAPH <$uri>");

    if ($schema_errors->isSuccessful()) {
      $this->messenger()->addMessage($this->t('No errors found during validation.'));
    }
    else {
      $this->messenger()->addMessage($this->t('%count schema error(s) were found while validating.', ['%count' => $schema_errors->errorCount()]), 'warning');
    }
    $form_state->set('validation_errors', $schema_errors);
  }

  /**
   * Retrieves the uploaded file.
   *
   * @return \Drupal\file\FileInterface|null
   *   File object, if one is uploaded.
   */
  protected function uploadedFile(): ?FileInterface {
    $file = file_save_upload(
      'adms_file',
      ['FileExtension' => ['extensions' => 'rdf ttl']],
      'public://',
      0
    );
    return $file instanceof FileInterface
      ? $file
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
