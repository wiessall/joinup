<?php

declare(strict_types = 1);

namespace Drupal\ventuno_helper\EventSubscriber;

use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\bootstrap4_modal\Ajax\CloseBootstrap4ModalDialogCommand;
use Drupal\bootstrap4_modal\Ajax\OpenBootstrap4ModalDialogCommand;
use Drupal\joinup_core\Event\AjaxCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to \Drupal\joinup_core\Event\AjaxCommandEvent event.
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
    ];
  }

  /**
   * Uses the Bootstrap 4 Modal dialog opener on Ventuno.
   *
   * @param \Drupal\joinup_core\Event\AjaxCommandEvent $event
   *   The Ajax command event.
   */
  public function onModalDialog(AjaxCommandEvent $event): void {
    $context = $event->getContext();
    if ($context['instance'] === 'join_collection.subscribe_dialog.open' && $this->isVentuno()) {
      $event->setCommand(new OpenBootstrap4ModalDialogCommand($context['title'], $context['content'], [
        'dialogClasses' => 'modal-dialog-centered',
        'dialogShowHeader' => TRUE,
        'dialogShowHeaderTitle' => TRUE,
      ]));
      $event->addAttachments(['library' => ['ventuno/modals']]);
    }
    if ($context['instance'] === 'join_collection.subscribe_dialog.close' && $this->isVentuno()) {
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
