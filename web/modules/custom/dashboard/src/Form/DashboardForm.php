<?php

declare(strict_types=1);

namespace Drupal\dashboard\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\joinup_core\Logger\Handler\MailHandler;
use Drupal\joinup_core\Logger\Handler\TelegramHandler;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Dashboard form.
 */
class DashboardForm extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected readonly StateInterface $state,
    #[Autowire(service: 'cache.page')]
    protected readonly CacheBackendInterface $pageCache,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'rdf_sync.connection')]
    protected readonly RdfSyncConnectionInterface $rdfSyncConnection,
    protected readonly Connection $db,
  ) {}

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
      '#access' => $this->currentUser()->hasPermission('administer log alerts'),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
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
      [
        '#title' => $this->t('Private messages reports'),
        '#url' => Url::fromRoute('view.private_message_report.all'),
      ],
      [
        '#title' => $this->t('Open Source Software Solutions'),
        '#url' => Url::fromRoute('view.eu_oss_catalogue_admin.admin'),
      ],
      [
        '#title' => $this->t('OAuth2 Client Requests'),
        '#url' => Url::fromRoute('view.oauth_client_request.admin_page'),
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
