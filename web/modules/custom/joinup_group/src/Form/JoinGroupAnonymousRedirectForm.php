<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\EventSubscriber\JoinGroupSubscriber;
use Drupal\rdf_entity\RdfInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form which directs anonymous users to EU Login so they can join a group.
 *
 * This form is shown in a modal dialog after an anonymous user presses the
 * button to join a group. It will set a cookie to keep track of the group that
 * the user wants to join. They will be automatically become a member when they
 * authenticate within 30 minutes after submitting this form.
 */
class JoinGroupAnonymousRedirectForm extends FormBase {

  /**
   * The group that is about to be joined by the user.
   *
   * @var \Drupal\joinup_group\Entity\GroupInterface
   */
  protected $group;

  /**
   * The time keeping service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a JoinGroupAnonymousRedirectForm.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time keeping service.
   */
  public function __construct(TimeInterface $time) {
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'join_group_anonymous_redirect';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $node = NULL): array {
    // Store the group on the object so it can be reused.
    $this->group = $node;

    $form = [
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        // Business analysts have determined that we should use different
        // terminology for joining the different group types.
        '#value' => $this->t('Only signed in users can @action. Please sign in or register an account on EU Login.', [
          '@action' => $this->group instanceof SolutionInterface ? $this->t('subscribe to this solution') : $this->t('join this collection'),
        ]),
      ],
      'actions' => [
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Sign in / Register'),
          '#button_type' => 'primary',
        ],
      ],
    ];

    // In case of a modal dialog, set the cancel button to simply close the
    // dialog.
    if ($this->isModal()) {
      $form['modal'] = [
        '#type' => 'hidden',
        '#value' => '1',
      ];
      $form['actions']['cancel'] = [
        '#type' => 'button',
        '#value' => $this->t('Cancel'),
        '#attributes' => ['class' => ['dialog-cancel']],
        // Put the cancel button to the left of the confirmation button so it is
        // consistent with the dialog shown when joining the group.
        '#weight' => -1,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Set a cookie remembering that the user wants to subscribe to this group,
    // valid for 30 minutes. If the user takes more time to sign in or register,
    // we assume they are no longer actively engaged in the joining process.
    $defaultExpire = $this->time->getRequestTime() + 30 * 60;
    setrawcookie(JoinGroupSubscriber::ID_COOKIE_NAME, rawurlencode($this->group->id()), $defaultExpire, '/');

    // If this was an ajax request, set another cookie to tell
    // JoinGroupSubscriber to redirect the user to the group page instead of
    // the subscribe form.
    $relationship = 'subscribe';
    if (!empty($form_state->getUserInput()['modal'])) {
      $relationship = 'canonical';
      setcookie(JoinGroupSubscriber::MODAL_SUBSCRIBE_COOKIE_NAME, '1', $defaultExpire, '/');
    }

    // Redirect to EU Login, with a query argument instructing to redirect back
    // to the group page.
    $url = (new Url('cas.login'))->setOption('query', [
      'destination' => $this->group->toUrl($relationship)->toString(),
    ]);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Route title callback. Returns the title of the authenticate to join modal.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The group that the anonymous user desires to join.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the modal form.
   */
  public function getTitle(NodeInterface $node): TranslatableMarkup {
    // Business analysts have indicated that specific terminology should be used
    // depending on the type of group that is being joined.
    if ($node instanceof SolutionInterface) {
      return $this->t('Sign in to subscribe');
    }
    return $this->t('Sign in to join');
  }

  /**
   * Returns whether the form is displayed in a modal.
   *
   * @return bool
   *   TRUE if the form is displayed in a modal.
   *
   * @todo Remove when issue #2661046 is in.
   *
   * @see https://www.drupal.org/node/2661046
   */
  protected function isModal(): bool {
    return $this->getRequest()->query->get(MainContentViewSubscriber::WRAPPER_FORMAT) === 'drupal_modal';
  }

}
