<?php

declare(strict_types=1);

namespace Drupal\contact_information\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\contact_information\Form\ContactInformationContactForm;
use Drupal\email_contact\Controller\ContactController;
use Drupal\honeypot\HoneypotServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controller extends ContactController from email_contact module.
 *
 * Allows to use a custom contact form that provides additional fields.
 */
class ContactInformationContactFormController extends ContactController {

  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
    FormBuilderInterface $formBuilder,
    EventDispatcherInterface $eventDispatcher,
    protected readonly MailManagerInterface $mailManager,
    #[Autowire(service: 'honeypot')]
    protected readonly ?HoneypotServiceInterface $honeypot,
  ) {
    parent::__construct(
      $logger, $entityTypeManager, $formBuilder, $eventDispatcher
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getForm(EntityInterface $entity, string $field_name, array $field_settings): array {
    return $this->formBuilder->getForm(
      new ContactInformationContactForm(
        $this->mailManager,
        $this->entityTypeManager,
        $this->honeypot,
        $entity->getEntityTypeId(),
        $entity->id(),
        $field_name,
        $field_settings,
      )
    );
  }

}
