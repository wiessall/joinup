<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\private_message\Entity\PrivateMessage;

/**
 * Service for private messages.
 */
class PrivateMessageService {

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Clear fields report_text and report_timestamp in reported private message.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $privateMessage
   *   Private message.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function flagAsOk(PrivateMessage $privateMessage): void {
    $privateMessage->set('report', '');
    $privateMessage->set('report_timestamp', NULL);
    $privateMessage->save();
  }

  /**
   * Gets path of private_message_report view page.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return string
   *   return view path.
   */
  public function getViewPath(): mixed {
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load('private_message_report')
      ->getExecutable();

    $view->setDisplay('page_2');
    $view->execute();
    return $view->getPath();
  }

}
