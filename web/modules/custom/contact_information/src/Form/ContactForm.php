<?php

declare(strict_types=1);

namespace Drupal\contact_information\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\email_contact\Plugin\Field\FieldFormatter\EmailContactLinkFormatter;
use Drupal\rdf_entity\RdfInterface;

/**
 * Extend email contact form.
 */
class ContactForm extends ContactInformationFormBase {

  /**
   * The collection group from the route.
   *
   * @var \Drupal\rdf_entity\RdfInterface
   */
  protected RdfInterface $rdfEntity;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?RdfInterface $rdf_entity = NULL): array {
    $this->rdfEntity = $rdf_entity;

    $form = parent::buildForm($form, $form_state);

    if (!$form_state->get('settings')) {
      $settings = EmailContactLinkFormatter::defaultSettings();
      $settings['default_message'] = '[current-user:name] sent a message using the contact form at [current-page:url].';
      $form_state->set('settings', $settings);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->sendMessage($form, $form_state);

    $form_state->setRedirect('entity.rdf_entity.canonical', [
      'rdf_entity' => $this->rdfEntity->id(),
    ]);
  }

  /**
   * Access check for the form.
   *
   * @param \Drupal\rdf_entity\RdfInterface $rdf_entity
   *   The collection for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(RdfInterface $rdf_entity): AccessResultInterface {
    return AccessResult::allowedIf(
      $this->currentUser()->isAuthenticated() &&
      !empty($rdf_entity->getValidatedContactInformationEmails())
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmailAddresses(): array {
    return $this->rdfEntity->getValidatedContactInformationEmails();
  }

}
