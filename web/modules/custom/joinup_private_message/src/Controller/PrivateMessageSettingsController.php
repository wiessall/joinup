<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Private message page controller. Returns render arrays for the page.
 */
class PrivateMessageSettingsController extends ControllerBase {

  /**
   * The form builder interface.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a PrivateMessageForm object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $formBuilder
   *   The form builder service.
   */
  public function __construct(FormBuilderInterface $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function privateMessageSettingsPage() {
    $form = $this->formBuilder->getForm('Drupal\joinup_private_message\Form\PrivateMessageSettingsForm');

    // Render the custom view block using views_embed_view().
    $view = Views::getView('private_message_ban_settings');
    if ($view) {
      // Use the correct block display ID.
      $view->setDisplay('block_1');
      $viewOutput = $view->render();
    }
    else {
      // Fallback if the view is not found.
      $viewOutput = ['#markup' => $this->t('View not found')];
    }

    // Return both the form and the block as part of the render array.
    return [
      '#prefix' => '<div id="private_message_configuration_page">',
      '#suffix' => '</div>',
      'form' => $form,
      'block' => [
        '#attributes' => ['class' => ['private-message-ban-list-block']],
        '#title' => $this->t('Blocked Users'),
        'content' => $viewOutput,
      ],
    ];
  }

}
