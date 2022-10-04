<?php

namespace Drupal\voc_comparer\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_rdf\VocabularyFixturesHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Voc comparer form.
 */
class VocabularyVersionCompareForm extends FormBase {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity type bundle information service.
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * The vocabulary fixtures helper service.
   */
  protected VocabularyFixturesHelperInterface $fixtureImporter;

  /**
   * Constructs a new VocabularyVersionCompareForm.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The entity type bundle information.
   * @param \Drupal\joinup_rdf\VocabularyFixturesHelperInterface $fixture_importer
   *   The vocabulary fixtures helper service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $bundle_info, VocabularyFixturesHelperInterface $fixture_importer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->bundleInfo = $bundle_info;
    $this->fixtureImporter = $fixture_importer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('joinup_rdf.vocabulary_fixtures.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'voc_comparer_vocabulary_version_compare';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['help_text'] = [
      '#type' => 'item',
      '#markup' => $this->t('Upload the new file below and select which vocabulary to test against.'),
    ];

    $form['file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('File'),
      '#description' => $this->t('Upload the new file to compare.'),
      '#upload_location' => 'public://voc_comparer',
      '#upload_validators' => [
        'file_validate_extensions' => ['rdf'],
      ],
      '#required' => TRUE,
    ];

    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#description' => $this->t('Select the vocabulary to test against.'),
      '#options' => $this->getVocabularyOptions(),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * Loads all taxonomy vocabularies as options.
   *
   * @return array
   *   An array with the vocabulary ID as key and the vocabulary name as value.
   */
  protected function getVocabularyOptions(): array {
    $options = [];
    $vocabularies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      $options[$vocabulary->id()] = $vocabulary->label();
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $file = $form_state->getValue('file');
    $filename = $this->entityTypeManager->getStorage('file')->load($file[0])->getFileUri();
    $vocabulary = $form_state->getValue('vocabulary');

    // Import the file into a custom temporary graph.
    $this->fixtureImporter->importFixtureToGraph($filename, "http://example.com/voc-comparer/{$vocabulary}");

    // Get the default graph for the vocabulary.
    // @todo This is probably not working.
    $graph = $this->bundleInfo->getBundleInfo('rdf_entity')[$vocabulary]['graph'];

    // @todo Run a list of queries to compare the two graphs and present the
    // results. Also, rebuild the form to show the results.
  }

}
