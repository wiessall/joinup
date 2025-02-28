<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\private_message\Entity\PrivateMessageInterface;
use Drupal\private_message\Form\PrivateMessageDeleteForm as OriginalPrivateMessageDeleteForm;
use Drupal\private_message\Service\PrivateMessageServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Overrides the form for deleting a private message.
 *
 * @ingroup private_message
 */
class PrivateMessageDeleteForm extends OriginalPrivateMessageDeleteForm {

  use AutowireTrait;

  public function __construct(
    EntityRepositoryInterface $entityRepository,
    EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    TimeInterface $time,
    #[Autowire(service: 'private_message.service')]
    protected PrivateMessageServiceInterface $privateMessageService,
  ) {
    parent::__construct($entityRepository, $entityTypeBundleInfo, $time);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    assert($this->entity instanceof PrivateMessageInterface);

    // Set the redirection depending if there are more threads in the page.
    $thread = $this->privateMessageService->getThreadFromMessage($this->entity);
    if (count($thread->getMessages()) > 1) {
      $form_state->setRedirectUrl($thread->toUrl());
    }
    else {
      $form_state->setRedirectUrl(Url::fromRoute('private_message.private_message_page'));
    }

    $this->entity->delete();
    $this->messenger()->addStatus($this->getDeletionMessage());
    $this->logDeletionMessage();
  }

}
