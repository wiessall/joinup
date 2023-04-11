<?php

declare(strict_types = 1);

namespace Drupal\ventuno_helper\EventSubscriber;

use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\bootstrap4_modal\Ajax\CloseBootstrap4ModalDialogCommand;
use Drupal\bootstrap4_modal\Ajax\OpenBootstrap4ModalDialogCommand;
use Drupal\email_contact\Event\AjaxEmailContactCommandEvent;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to AjaxCommandEvent|AjaxEmailContactCommandEvent event.
 */
class VentunoOpenModalDialogSubscriber implements EventSubscriberInterface {

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager service.
   */
  public function __construct(ThemeManagerInterface $theme_manager) {
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      AjaxCommandEvent::class => 'onModalDialog',
      AjaxEmailContactCommandEvent::class => 'onModalDialog',
    ];
  }

  /**
   * Uses the Bootstrap 4 Modal dialog opener on Ventuno.
   *
   * @param \Drupal\joinup_core\Event\AjaxCommandEvent|\Drupal\email_contact\Event\AjaxEmailContactCommandEvent $event
   *   The Ajax command event.
   */
  public function onModalDialog(AjaxCommandEvent|AjaxEmailContactCommandEvent $event): void {
    $context = $event->getContext();
    $instance_open = [
      'join_collection.subscribe_dialog.open',
      'email_contact.modal.open',
    ];
    $instance_close = [
      'join_collection.subscribe_dialog.close',
      'email_contact.modal.close',
    ];
    if (in_array($context['instance'], $instance_open) && $this->isVentuno()) {
      $event->setCommand(new OpenBootstrap4ModalDialogCommand($context['title'], $context['content'], [
        'dialogClasses' => ['modal-dialog-centered', 'modal-lg'],
        'dialogShowHeader' => TRUE,
        'dialogShowHeaderTitle' => TRUE,
      ]));
      $event->addAttachments(['library' => ['ventuno/modals']]);
    }
    if (in_array($context['instance'], $instance_close) && $this->isVentuno()) {
      $event->setCommand(new CloseBootstrap4ModalDialogCommand());
    }

  }

  /**
   * Checks whether the current theme is Ventuno.
   *
   * @return bool
   *   Whether the current theme is Ventuno.
   */
  protected function isVentuno(): bool {
    return $this->themeManager->getActiveTheme()->getName() === 'ventuno';
  }

}
