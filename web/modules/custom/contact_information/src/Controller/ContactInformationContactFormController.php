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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Controller extends ContactController from email_contact module.
 *
 * Allows to use a custom contact form that provides additional fields.
 */
class ContactInformationContactFormController extends ContactController {

  /**
   * Constructs a new contact information contact form controller.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   Form builder.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   * @param \Drupal\Core\Mail\MailManagerInterface $mailManager
   *   Email manager.
   * @param \Drupal\honeypot\HoneypotServiceInterface|null $honeypot
   *   Honeypot service.
   */
  public function __construct(
    LoggerInterface $logger,
    EntityTypeManagerInterface $entityTypeManager,
    FormBuilderInterface $formBuilder,
    EventDispatcherInterface $eventDispatcher,
    protected MailManagerInterface $mailManager,
    protected ?HoneypotServiceInterface $honeypot,
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
      $container->get('plugin.manager.mail'),
      $container->has('honeypot') ? $container->get('honeypot') : NULL
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
