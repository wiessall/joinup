<?php

declare(strict_types=1);

namespace Drupal\dashboard\Form;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\joinup_core\ConfigReadOnlyInterface;
use Drupal\joinup_core\Logger\Handler\MailHandler;
use Drupal\joinup_core\Logger\Handler\TelegramHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard form.
 */
class DashboardForm extends FormBase {

  public function __construct(
    protected StateInterface $state,
    protected ConfigReadOnlyInterface $fileConfigReadOnly,
    protected CacheTagsInvalidatorInterface $invalidator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('state'),
      $container->get('joinup_core.file_config_read_only'),
      $container->get('cache_tags.invalidator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['links'] = [
      '#theme' => 'item_list',
      '#items' => $this->getLinks(),
      '#title' => $this->t('Tasks'),
    ];

    $log_alerts = $this->state->get('joinup.log_alerts', $this->getLogAlertsDefaultSettings());

    // Builds a level form element.
    $level = function (string $key) use ($log_alerts): array {
      return [
        '#type' => 'select',
        '#title' => $this->t('Minimum level to be logged'),
        '#options' => [0 => $this->t('- Select -')] + MailHandler::getSeverityLevels(),
        '#default_value' => $log_alerts[$key]['level'],
      ];
    };

    $form['log_alerts'] = [
      '#type' => 'details',
      '#title' => $this->t('Log alerts'),
      '#tree' => TRUE,
      '#access' => $this->currentUser()->hasPermission('administer log alerts'),
      'mail' => [
        '#type' => 'details',
        '#title' => $this->t('Email'),
        'to' => [
          '#type' => 'textfield',
          '#title' => $this->t('Recipients (comma separated)'),
          '#default_value' => $log_alerts['mail']['to'] ?? '',
        ],
        'level' => $level('mail'),
      ],
      'telegram' => [
        '#type' => 'details',
        '#title' => $this->t('Telegram'),
        'apiKey' => [
          '#type' => 'textfield',
          '#title' => $this->t('JoinupDevBot API key'),
          '#default_value' => $log_alerts['telegram']['apiKey'] ?? '',
        ],
        'channel' => [
          '#type' => 'textfield',
          '#title' => $this->t('Chat ID'),
          '#default_value' => $log_alerts['telegram']['channel'] ?? '',
        ],
        'level' => $level('telegram'),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#access' => $this->getFormElementsAccess($form),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    $hasEnableDisablePermission = $this->currentUser()->hasPermission('enable/disable config readonly');
    $form['actions']['enable'] = [
      '#type' => 'submit',
      '#value' => $this->t('Enable config readonly'),
      '#submit' => ['::enableConfigSubmitForm'],
      '#access' => $hasEnableDisablePermission && !$this->fileConfigReadOnly->isReadOnlyEnabled(),
    ];
    $form['actions']['disable'] = [
      '#type' => 'submit',
      '#value' => $this->t('Disable config readonly'),
      '#submit' => ['::disableConfigSubmitForm'],
      '#access' => $hasEnableDisablePermission && $this->fileConfigReadOnly->isReadOnlyEnabled(),
    ];

    return $form;
  }

  /**
   * Enable the configuration submit form.
   */
  public function enableConfigSubmitForm(): void {
    $this->fileConfigReadOnly->enableReadOnly();
    $this->invalidator->invalidateTags(['joinup_user:read_only_config']);
    $this->messenger()->addStatus($this->t('Enabled config readonly'));
  }

  /**
   * Disables the config submit form.
   */
  public function disableConfigSubmitForm(): void {
    $this->fileConfigReadOnly->disableReadOnly();
    $this->invalidator->invalidateTags(['joinup_user:read_only_config']);
    $this->messenger()->addStatus($this->t('Disabled config readonly'));
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $log_alerts = $form_state->getValue('log_alerts');
    if ($log_alerts !== $this->state->get('log_alerts', $this->getLogAlertsDefaultSettings())) {
      $this->state->set('joinup.log_alerts', $log_alerts);
      $this->messenger()->addStatus($this->t('Log alerts changes were saved'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dashboard_form';
  }

  /**
   * Returns true when at least one form element is accessible.
   *
   * @param array $element
   *   The form element. This method is called recursively, but on the first
   *   call, $element is always the whole form array.
   *
   * @return bool
   *   Whether at least one form element is accessible.
   */
  protected function getFormElementsAccess(array $element): bool {
    static $is_form = TRUE;

    $keys = Element::children($element);

    if ($is_form && empty($keys)) {
      return FALSE;
    }
    elseif (!$is_form && array_key_exists('#access', $element)) {
      return $element['#access'] instanceof AccessResultInterface ? $element['#access']->isAllowed() : $element['#access'];
    }
    elseif (empty($keys)) {
      return TRUE;
    }
    $is_form = FALSE;

    $access = FALSE;
    foreach ($keys as $key) {
      if ($this->getFormElementsAccess($element[$key])) {
        $access = TRUE;
        break;
      }
    }
    return $access;
  }

  /**
   * Returns a list of links to be displayed on the form.
   *
   * @return array
   *   A list of links.
   */
  protected function getLinks(): array {
    $links = [
      [
        '#title' => $this->t('My subscriptions'),
        '#url' => Url::fromRoute('joinup_subscription.my_subscriptions', [
          'user' => $this->currentUser()->id(),
        ]),
      ],
      [
        '#title' => $this->t('Licences overview'),
        '#url' => Url::fromRoute('view.licences.list'),
      ],
      [
        '#title' => $this->t('Curated content listings'),
        '#url' => Url::fromRoute('view.curated_content_listings.page'),
      ],
      [
        '#title' => $this->t('Custom landing pages'),
        '#url' => Url::fromRoute('view.content.landing_pages'),
      ],
      [
        '#title' => $this->t('Topics'),
        '#url' => Url::fromRoute('view.topic_meta_entity.page'),
      ],
      [
        '#title' => $this->t('Files'),
        '#url' => Url::fromRoute('view.files.page_1'),
      ],
      [
        '#title' => $this->t('Manage webforms'),
        '#url' => Url::fromRoute('entity.webform.collection'),
      ],
    ];

    $links = array_map(function (array $link): array {
      return $link + ['#type' => 'link'];
    }, $links);

    return array_filter($links, function (array $link): bool {
      return $link['#url']->access();
    });
  }

  /**
   * Returns the log alerts default settings.
   *
   * @return array
   *   The log alerts default settings.
   */
  protected function getLogAlertsDefaultSettings(): array {
    return [
      'mail' => MailHandler::getDefaultSettings(),
      'telegram' => TelegramHandler::getDefaultSettings(),
    ];
  }

}
