<?php

declare(strict_types = 1);

namespace Drupal\joinup_rdf\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_rdf\VocabularyFixturesHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Joinup RDF form.
 */
final class VocabularyImporterForm extends FormBase {

  /**
   * The vocabulary fixtures helper.
   *
   * @var \Drupal\joinup_rdf\VocabularyFixturesHelperInterface $vocabularyFixturesHelper
   *   The vocabulary fixtures helper.
   */
  public function __construct(protected VocabularyFixturesHelperInterface $vocabularyFixturesHelper) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('joinup_rdf.vocabulary_fixtures.helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_rdf_vocabulary_importer';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $data = $this->vocabularyFixturesHelper->getFixturesData();
    foreach ($data as $vocabulary => $info) {
      $vocabularies[$vocabulary] = ucfirst(str_replace(['_', '-'], ' ', $vocabulary));
    }

    $form['vocabulary'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#options' => $vocabularies,
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Import'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $vocabulary = $form_state->getValue('vocabulary');

    try {
      $this->vocabularyFixturesHelper->importFixtures($vocabulary);
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('An error occurred while importing the vocabulary: @error', [
        '@error' => $e->getMessage(),
      ]));
      return;
    }

    $this->messenger()->addStatus($this->t('Import was completed with no errors.'));
  }

}
