<?php

declare(strict_types = 1);

namespace Drupal\joinup_acceptance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\joinup_acceptance\SendSpooledEmailsInterface;
use Drupal\message_digest\Traits\MessageDigestTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form allowing to perform acceptance tasks.
 */
class AcceptanceTasksForm extends FormBase {

  use MessageDigestTrait;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The queue manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected QueueWorkerManagerInterface $queueManager;

  /**
   * Helper service to send spooled emails.
   *
   * @var \Drupal\joinup_acceptance\SendSpooledEmailsInterface
   */
  protected SendSpooledEmailsInterface $sendSpooledEmails;

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   The queue service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_manager
   *   The queue manager.
   * @param \Drupal\joinup_acceptance\SendSpooledEmailsInterface $send_spool
   *   Helper service to send spooled emails.
   */
  public function __construct(QueueFactory $queue_factory, QueueWorkerManagerInterface $queue_manager, SendSpooledEmailsInterface $send_spool) {
    $this->queueFactory = $queue_factory;
    $this->queueManager = $queue_manager;
    $this->sendSpooledEmails = $send_spool;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('send_spool.emails')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['deliver'] = [
      '#type' => 'submit',
      '#value' => $this->t('Deliver message digests'),
      '#submit' => [[$this, 'deliverDigestMessages']],
    ];
    $form['send_spool'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send spooled emails'),
      '#submit' => [[$this, 'sendSpoolEmails']],
    ];
    return $form;
  }

  /**
   * Provides a submit handler for digest messages delivery button.
   *
   * @param array $form
   *   The form API render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state API object.
   */
  public function deliverDigestMessages(array &$form, FormStateInterface $form_state): void {
    $this->expireDigestMessages();
    do {
      $this->expireMessageDigestNotifiers();
      message_digest_cron();
      if (!$this->processQueue()) {
        // An error status message has already been set.
        return;
      }
    } while ($this->countAllUndeliveredDigestMessages());
    $this->messenger()->addStatus('Successfully delivered all digest messages. Note that the messages might have been spooled, meaning it could take some time to receive them.');
  }

  /**
   * Provides a submit handler for the "Send spooled email" button.
   *
   * @param array $form
   *   The form API render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state API object.
   */
  public function sendSpoolEmails(array &$form, FormStateInterface $form_state): void {
    $this->sendSpooledEmails->sendSpool("sendmail");
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'acceptance_tasks';
  }

  /**
   * Processes 'message_digest' queue.
   *
   * @return bool
   *   The queue process success.
   */
  protected function processQueue(): bool {
    $this->queueFactory->get('message_digest')->createQueue();
    $queue_worker = $this->queueManager->createInstance('message_digest');
    $queue = $this->queueFactory->get('message_digest');
    $error_status_message = $this->t("Errors were logged while delivering digest messages. Contact the site administrator.");
    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (RequeueException $e) {
        // The worker requested the task be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // The worker indicates there is a problem with the whole queue. Exit.
        $queue->releaseItem($item);
        watchdog_exception('message_digest', $e);
        $this->messenger()->addError($error_status_message);
        return FALSE;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        watchdog_exception('message_digest', $e);
        $this->messenger()->addError($error_status_message);
        return FALSE;
      }
    }
    return TRUE;
  }

}
