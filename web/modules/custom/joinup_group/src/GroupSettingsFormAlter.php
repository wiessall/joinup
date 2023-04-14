<?php

declare(strict_types = 1);

namespace Drupal\joinup_group;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_page\CustomPageOgMenuLinksManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Provide implementation of "joinup_group.group_settings_form_alter" service.
 */
class GroupSettingsFormAlter implements GroupSettingsFormAlterInterface {

  use StringTranslationTrait;

  /**
   * Builds a new group settings form alter service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\custom_page\CustomPageOgMenuLinksManagerInterface $customPageOgMenuLinksManager
   *   The custom page menu link manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected CustomPageOgMenuLinksManagerInterface $customPageOgMenuLinksManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    $link = $this->getMenuLinkContent($form_state);

    if ($link) {
      $form['tab_overview'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Overview label'),
        '#description' => $this->t('It overwrites the default label for the "Overview" tab'),
        '#default_value' => $link->getTitle(),
        '#weight' => 10,
        '#required' => TRUE,
      ];

      $form['actions']['submit']['#submit'][] = [$this, 'submitCallback'];
    }
  }

  /**
   * Gets menu link content.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   Menu link content.
   */
  protected function getMenuLinkContent(FormStateInterface $form_state): ?MenuLinkContentInterface {
    /** @var \Drupal\rdf_entity\RdfInterface $rdf */
    $rdf = $form_state->getFormObject()?->getEntity()?->getTargetEntity();
    if (!$rdf) {
      return NULL;
    }

    $menu = $this->customPageOgMenuLinksManager->getOgMenuInstanceByGroupId($rdf->id());
    if (!$menu) {
      return NULL;
    }

    /** @var  \Drupal\menu_link_content\MenuLinkContentStorageInterface $menuLinkContentStorage */
    $menuLinkContentStorage = $this->entityTypeManager->getStorage('menu_link_content');

    // Collect the IDs of links to the custom page.
    $ids = $menuLinkContentStorage->getQuery()->accessCheck(FALSE)
      ->condition('bundle', 'menu_link_content')
      ->condition('menu_name', "ogmenu-{$menu->id()}")
      ->condition('link.uri', 'entity:rdf_entity/' . UriEncoder::encodeUrl($rdf->id()))
      ->range(0, 1)
      ->execute();
    if (empty($ids)) {
      return NULL;
    }

    /** @var \Drupal\menu_link_content\MenuLinkContentInterface $entity */
    $entity = $menuLinkContentStorage->load(reset($ids));
    if (!$entity) {
      return NULL;
    }

    return $entity;
  }

  /**
   * Submission callback.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitCallback(array $form, FormStateInterface $form_state): void {
    $link = $this->getMenuLinkContent($form_state);
    if ($link) {
      $link->set('title', $form_state->getValue('tab_overview'));
      $link->save();
    }
  }

}
