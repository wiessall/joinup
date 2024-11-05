<?php

declare(strict_types=1);

namespace Drupal\contact_information\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\email_contact\Plugin\Field\FieldFormatter\EmailContactLinkFormatter;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\node\NodeInterface;

/**
 * Extend email contact form.
 */
class ContactForm extends ContactInformationFormBase {

  /**
   * The group entity from the route.
   *
   * @var \Drupal\joinup_group\Entity\GroupInterface
   */
  protected GroupInterface $group;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    assert($node instanceof GroupInterface);
    $this->group = $node;

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

    $form_state->setRedirectUrl($this->group->toUrl());
  }

  /**
   * Access check for the form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The collection entity for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    return AccessResult::allowedIf(
      $node instanceof GroupInterface &&
      $this->accessIsAllowed() &&
      !empty($node->getValidatedContactInformationEmails())
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmailAddresses(): array {
    return $this->group->getValidatedContactInformationEmails();
  }

}
