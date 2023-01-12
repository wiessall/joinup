<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\contact_information\ContactInformationConfirmAccessInterface;
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\contact_information\Entity\ContactInformation;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns the response for the contact form route.
 */
class EmailConfirmController extends ControllerBase {

  /**
   * Constructs a new EmailConfirmController object.
   *
   * @param \Drupal\contact_information\ContactInformationEmailConfirmInterface $contact_information_email_confirm
   *   The contact information email confirm service.
   * @param \Drupal\contact_information\ContactInformationConfirmAccessInterface $contact_information_email_confirm_access
   *   The contact information email confirm service.
   */
  public function __construct(protected ContactInformationEmailConfirmInterface $contact_information_email_confirm, protected ContactInformationConfirmAccessInterface $contact_information_email_confirm_access) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('contact_information.email_confirm'),
      $container->get('contact_information.email_confirm_access')
    );
  }

  /**
   * Confirm an email action.
   *
   * @param \Drupal\contact_information\Entity\ContactInformation $contact_information
   *   Contact Information entity.
   *
   * @return array
   *   The render array containing the contact form.
   */
  public function confirm(ContactInformation $contact_information) {

    if (!$contact_information->get('field_ci_email')->isEmpty()) {
      $emailValue = $contact_information->get('field_ci_email')->value;
      $confirm = $this->contact_information_email_confirm->resendEmail($emailValue);
      if ($confirm) {
        $this->messenger()->addStatus($this->t('An email has been sent to the registered email to validate your contact information.'));
        $url = Url::fromRoute('entity.rdf_entity.about_page', ['rdf_entity' => $contact_information->getRelatedGroup()->id()]);
        return new RedirectResponse($url->toString());
      }
    }
  }

  /**
   * Access check for the confirmation page.
   *
   * @param \Drupal\contact_information\Entity\ContactInformation $contact_information
   *   Contact Information entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(ContactInformation $contact_information): AccessResultInterface {
    return $this->contact_information_email_confirm_access->access($contact_information);
  }

}
