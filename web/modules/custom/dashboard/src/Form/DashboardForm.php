<?php

declare(strict_types = 1);

namespace Drupal\dashboard\Form;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dashboard form.
 */
class DashboardForm extends FormBase {

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(protected StateInterface $state) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('state'));
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

    $form['cas'] = [
      '#type' => 'details',
      '#title' => $this->t('Users registration/login'),
      '#open' => TRUE,
      'registration_disabled' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Disable new accounts registration'),
        '#default_value' => $this->state->get('joinup_eulogin.registration_disabled', FALSE),
        '#access' => $this->currentUser()->hasPermission('manage site auto-registration'),
      ],
      'accounts_approve' => [
        '#type' => 'checkbox',
        '#title' => $this->t('New accounts need approval'),
        '#default_value' => $this->state->get('joinup_eulogin.accounts_approve', FALSE),
        '#access' => $this->currentUser()->hasPermission('manage site auto-registration'),
      ],
      'debug' => [
        '#type' => 'checkbox',
        '#title' => $this->t('CAS debug mode'),
        '#default_value' => $this->state->get('joinup_eulogin.cas_debug', FALSE),
        '#access' => $this->currentUser()->hasPermission('cas debug mode'),
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#access' => $this->getFormElementsAccess($form),
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $registration_disabled = (bool) $form_state->getValue(['registration_disabled']);
    if ($registration_disabled !== $form['cas']['registration_disabled']['#default_value']) {
      if ($registration_disabled) {
        $this->state->set('joinup_eulogin.registration_disabled', TRUE);
        $this->messenger()->addWarning($this->t('Registering new users to Joinup is disabled'));
      }
      else {
        $this->state->delete('joinup_eulogin.registration_disabled');
        $this->messenger()->addStatus($this->t('Registering new users to Joinup is enabled'));
      }
    }
    $accounts_approve = (bool) $form_state->getValue(['accounts_approve']);
    if ($accounts_approve !== $form['cas']['accounts_approve']['#default_value']) {
      if ($accounts_approve) {
        $this->state->set('joinup_eulogin.accounts_approve', TRUE);
        $this->messenger()->addWarning($this->t('Registering new users to Joinup requires approval is enabled'));
      }
      else {
        $this->state->delete('joinup_eulogin.accounts_approve');
        $this->messenger()->addStatus($this->t('Registering new users to Joinup requires approval is disabled'));
      }
    }
    $cas_debug = (bool) $form_state->getValue(['debug']);
    if ($cas_debug !== $form['cas']['debug']['#default_value']) {
      if ($cas_debug) {
        $this->state->set('joinup_eulogin.cas_debug', TRUE);
        $this->messenger()->addWarning($this->t('CAS debug mode enabled'));
      }
      else {
        $this->state->delete('joinup_eulogin.cas_debug');
        $this->messenger()->addStatus($this->t('CAS debug mode disabled'));
      }
      $this->configFactory()->reset('cas.settings');
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
        '#url' => Url::fromRoute('joinup_licence.overview'),
      ],
      [
        '#title' => $this->t('Curated content listings'),
        '#url' => Url::fromRoute('view.curated_content_listings.page'),
      ],
    ];

    $links = array_map(function (array $link): array {
      return $link + ['#type' => 'link'];
    }, $links);

    return array_filter($links, function (array $link): bool {
      return $link['#url']->access();
    });
  }

}
