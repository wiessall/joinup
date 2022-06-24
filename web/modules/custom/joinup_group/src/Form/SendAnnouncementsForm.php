<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\message\Entity\Message;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to send an announcement message.
 */
class SendAnnouncementsForm extends FormBase {

  /**
   * The group that is about to be joined by the user.
   *
   * @var \Drupal\joinup_group\Entity\GroupInterface
   */
  protected GroupInterface $group;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Builds a new form instance.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(Token $token, ModuleHandlerInterface $module_handler) {
    $this->token = $token;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('token'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_announcement_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $rdf_entity = NULL): array {
    $this->group = $rdf_entity;
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['rdf_entity'],
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#element_validate' => ['token_element_validate'],
      '#token_types' => ['rdf_entity'],
      '#required' => TRUE,
      '#cols' => 100,
      '#rows' => 10,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['tokens'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['rdf_entity'],
        '#global_types' => TRUE,
        '#dialog' => TRUE,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $token_data = ['rdf_entity' => $this->group];
    $message = Message::create(['template' => 'announcement']);
    $message->set('group_reference', $this->group->id());
    $message->set('subject', $this->token->replacePlain($form_state->getValue('subject'), $token_data));
    $message->set('body', $this->token->replacePlain($form_state->getValue('body'), $token_data));
    $message->set('recipients', $this->group->getMembers());
    $message->save();
  }

}
