<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\sparql_entity_storage\UriEncoder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form that redirects to a RDF entity page.
 */
class RdfEntityUriWebConverterForm extends FormBase {

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF Sync mapper service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RdfSyncMapper $mapper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('rdf_sync.mapper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['entity_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RDF entity ID or a URL'),
      '#maxlength' => 2048,
      '#attributes' => ['size' => 2048],
      '#description' => $this->t('Paste either a RDF entity ID in order to be redirected to the RDF entity page, or a system or aliased RDF entity URL to get the decoded ID of the entity,'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Go!'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    global $base_url;
    parent::validateForm($form, $form_state);

    $id = $path = trim($form_state->getValue('entity_id'));

    // An absolute URL.
    if (UrlHelper::isExternal($id)) {
      if (!UrlHelper::externalIsLocal($id, $base_url)) {
        $entity_type_id = NULL;

        // Potential RDF entity ID.
        if ($this->entityTypeManager->getStorage('rdf_entity')->load($id)) {
          $entity_type_id = 'rdf_entity';
        }

        // It might be a mapped taxonomy term.
        if (($term = $this->mapper->getEntityByUri($id)) && $term->getEntityTypeId() === 'taxonomy_term') {
          $entity_type_id = 'taxonomy_term';
          $form_state->setValue('entity_id', $term->id());
        }

        if ($entity_type_id) {
          $form_state->set('passed_as', 'id');
          $form_state->set('entity_type', $entity_type_id);
        }
        else {
          $form_state->setErrorByName('id', $this->t('Not a valid RDF ID: :id.', [':id' => $id]));
        }
        return;
      }
      // Extract the path from the absolute URL.
      $path = parse_url($id, PHP_URL_PATH);
    }

    // Build a Drupal internal URI.
    $uri = 'internal:/' . trim($path, "/");
    $url = Url::fromUri($uri);
    // The path was build from a route.
    if ($url->isRouted()) {
      // This path contains an RDF parameter.
      $parameters = $url->getRouteParameters();
      if (!empty($parameters['rdf_entity'])) {
        $form_state->set('passed_as', 'url');
        $form_state->setValue('entity_id', UriEncoder::decodeUrl($parameters['rdf_entity']));
        return;
      }
    }
    $form_state->setErrorByName('id', $this->t('The entered value (:id) is neither a valid RDF entity ID, nor a valid RDF taxonomy ID, nor a RDF entity URL.', [':id' => $id]));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $passed_as = $form_state->get('passed_as');
    $id = trim($form_state->getValue('entity_id'));
    $entity_type_id = $form_state->get('entity_type');
    if ($passed_as === 'id') {
      $form_state->setRedirect("entity.$entity_type_id.canonical", [
        $entity_type_id => $id,
      ]);
    }
    elseif ($passed_as === 'url') {
      // We display only the ID, so it can be easily selected with a mouse
      // double/triple click and then copied.
      $this->messenger()->addMessage($id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'rdf_entity_uri_web_converter';
  }

}
