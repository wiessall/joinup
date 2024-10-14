<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_private_message\Service\PrivateMessageService;
use Drupal\private_message\Entity\PrivateMessage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller for private message actions.
 */
class PrivateMessageActionsController extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function __construct(protected PrivateMessageService $privateMessageService) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('joinup_private_message.private_message_service')
    );
  }

  /**
   * Clear fields report_text and report_timestamp in reported private message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $private_message
   *   Private message.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response.
   */
  public function flagAsOk(PrivateMessage $private_message): RedirectResponse {
    $this->privateMessageService->flagAsOk($private_message);
    return $this->redirect('private_message.private_message_page');
  }

}
