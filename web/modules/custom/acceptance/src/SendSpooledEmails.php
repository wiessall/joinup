<?php

declare(strict_types = 1);

namespace Drupal\joinup_acceptance;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\swiftmailer\TransportFactoryInterface;

/**
 * Consume the emails in spool.
 */
class SendSpooledEmails implements SendSpooledEmailsInterface {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * A list of transport systems that can deliver mails from the spool.
   *
   * @var string[]
   */
  protected static array $validtransports = [
    SWIFTMAILER_TRANSPORT_SMTP,
    SWIFTMAILER_TRANSPORT_SENDMAIL,
    SWIFTMAILER_TRANSPORT_NULL,
  ];

  /**
   * The transport factory service.
   *
   * @var \Drupal\swiftmailer\TransportFactoryInterface
   */
  protected TransportFactoryInterface $transportFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(TransportFactoryInterface $transport_factory) {
    $this->transportFactory = $transport_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function sendSpool(string $transport = "null"): void {
    if (in_array($transport, self::$validtransports, TRUE)) {
      $spool_transport = $this->transportFactory->getTransport(SWIFTMAILER_TRANSPORT_SPOOL);
      $swift_transport = $this->transportFactory->getTransport($transport);
      $spool_transport->getSpool()->flushQueue($swift_transport);
      $this->messenger()->addStatus('Successfully delivered all emails. Note that the emails might have been spooled, meaning it could take some time to receive them.');
    }
    else {
      $this->messenger()->addError($this->t('Invalid transport type: "%type". Valid types are %types.', [
        '%type' => $transport,
        '%types' => implode(', ', self::$validtransports),
      ]));
    }

  }

}
