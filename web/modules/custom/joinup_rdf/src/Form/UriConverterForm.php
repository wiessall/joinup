<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_rdf\EventSubscriber\LegacyRdfUrlSubscriber;
use Drupal\rdf_sync\RdfSyncMapperInterface;

/**
 * Simple form converting a URI to entity URL and vice versa.
 */
class UriConverterForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RdfSyncMapperInterface $mapper,
    protected readonly LegacyRdfUrlSubscriber $legacyRdfUrl,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('RDF URI or entity URL'),
      '#maxlength' => 2048,
      '#attributes' => ['size' => 2048],
      '#description' => $this->t('Either a RDF URI in order to be redirected to the entity page, or a system or aliased entity URL to get the RDF URI of the entity.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Go!'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uri = $path = trim($form_state->getValue('uri'));

    if (UrlHelper::isExternal($uri)) {
      // Potential RDF mapped entity URI.
      if ($entity = $this->mapper->getEntityByUri($uri)) {
        $form_state->setRedirectUrl($entity->toUrl());
        return;
      }
      $path = parse_url($uri, PHP_URL_PATH);
    }

    // Build a Drupal internal URI.
    $internalUri = 'internal:/' . trim($path, "/");
    $url = Url::fromUri($internalUri);
    $entity = NULL;

    if (!$url->isRouted() || !preg_match('/^entity.(node|taxonomy_term).canonical$/', $url->getRouteName(), $match)) {
      // Maybe it's a legacy URL.
      [$entity] = $this->legacyRdfUrl->getTargetEntity($path);
    }
    else {
      $entityTypeId = $match[1];
      if (($entityId = $url->getRouteParameters()[$entityTypeId] ?? NULL)) {
        $entity = $this->entityTypeManager->getStorage($entityTypeId)
          ->load($entityId);
      }
    }

    if ($entity instanceof RdfSyncEntityInterface) {
      $arguments = [
        '@label' => $entity->getEntityType()->getLabel(),
        '@id' => $entity->id(),
        '@uri' => $entity->getUri(),
        '@uuid' => $entity->uuid(),
      ];
      $this->messenger()->addStatus($this->t('@label ID: @id', $arguments));
      $this->messenger()->addStatus($this->t('@label URI: @uri', $arguments));
      $this->messenger()->addStatus($this->t('@label UUID: @uuid', $arguments));
      return;
    }

    $this->messenger()
      ->addError($this->t('Cannot convert @uri', ['@uri' => $uri]));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_uri_converter_form';
  }

}
