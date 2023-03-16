<?php

declare(strict_types = 1);

namespace Drupal\contact_information\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\contact_information\Form\ContactInformationContactForm;
use Drupal\email_contact\Controller\ContactController;
use Drupal\honeypot\HoneypotServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controller extends ContactController from email_contact module.
 *
 * Controls controller to make our one in order to add new fields in the email.
 */
class ContactInformationContactController extends ContactController {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
    FormBuilderInterface $formBuilder,
    EventDispatcherInterface $eventDispatcher,
    protected HoneypotServiceInterface $honeypotService,
    protected MailManagerInterface $mailManager
  ) {
    parent::__construct(
      $logger, $entityTypeManager, $formBuilder, $eventDispatcher
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory')->get('action'),
      $container->get('entity_type.manager'),
      $container->get('form_builder'),
      $container->get('event_dispatcher'),
      $container->get('honeypot'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getForm(EntityInterface $entity, string $field_name, array $field_settings): array {
    return $this->formBuilder->getForm(new ContactInformationContactForm(
      $this->honeypotService,
      $this->mailManager,
      $this->moduleHandler(),
      $this->entityTypeManager,
      $entity->getEntityTypeId(),
      $entity->id(),
      $field_name,
      $field_settings,
    ));
  }

}
