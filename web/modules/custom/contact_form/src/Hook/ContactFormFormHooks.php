<?php

declare(strict_types=1);

namespace Drupal\contact_form\Hook;

use Drupal\contact_form\ContactFormEvents;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\honeypot\HoneypotServiceInterface;
use Drupal\joinup_notification\Event\NotificationEvent;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Hook implementations for contact form form.
 */
final class ContactFormFormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly EventDispatcherInterface $eventDispatcher,
    protected readonly MessengerInterface $messenger,
    #[Autowire(service: 'logger.channel.joinup_core')]
    protected readonly LoggerChannelInterface $logger,
    protected readonly AccountInterface $currentUser,
    protected readonly ModuleHandlerInterface $moduleHandler,
    #[Autowire(service: 'honeypot')]
    protected readonly HoneypotServiceInterface $honeypotService,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * This changes the contact form which is provided by the
   * 'contact_form_submission' message template:
   *  - Prefill the user's name and e-mail address if the user is logged in.
   *  - Only show the 'Page URL' field when the 'bug' or 'report' category is
   *    chosen.
   *  - Change the text of the submit button to "Submit".
   *  - Add a submit handler that sends notifications to moderators and shows a
   *    success message informing the user that their message has been
   *    successfully sent.
   */
  #[Hook('form_message_contact_form_submission_form_alter')]
  public function formAlter(&$form, FormStateInterface $formState, string $formId): void {
    $arguments = [
      ':faq-url' => Url::fromUri('base:/collection/joinup/faq')->toString(),
    ];
    $form['#prefix'] = '<p>' . (string) $this->t('Before you submit your request check our <a href=":faq-url">FAQ</a> section in case it covers your query/issue.', $arguments) . '</p>';

    // Fill in the credentials if the user is logged in.
    if ($this->currentUser->isAuthenticated()) {
      $user = User::load($this->currentUser->id());
      $form['field_contact_first_name']['widget'][0]['value']['#default_value'] = $user->get('field_user_first_name')->value;
      $form['field_contact_last_name']['widget'][0]['value']['#default_value'] = $user->get('field_user_family_name')->value;
      $form['field_contact_email']['widget'][0]['value']['#default_value'] = $user->getEmail();
    }

    // Update the category options. "_none" category is kept as it is. Most of
    // remaining categories will be grouped as "Interoperable Europe" and
    // finally, "legal" "question_feature", "bug", "report" as "Other".
    $options = [];
    $ie_group = (string) $this->t('Interoperable Europe');
    $other_group = (string) $this->t('Other');
    $ie_options = [
      'academy',
      'architecture',
      'assessments',
      'community',
      'eu_oss_catalogue',
      'governance',
      'govtech',
      'monitoring',
      'open_source',
      'semantics',
    ];
    foreach ($form['field_contact_category']['widget']['#options'] as $key => $human_label) {
      if ($key === '_none') {
        $options[$key] = $human_label;
      }
      elseif (in_array($key, $ie_options, TRUE)) {
        $options[$ie_group][$key] = $human_label;
      }
      else {
        $options[$other_group][$key] = $human_label;
      }
    }

    $form['field_contact_category']['widget']['#options'] = $options;

    // Only show the 'Page URL' field when the 'bug' or 'report' category is
    // chosen.
    $form['field_contact_url']['#states'] = [
      'visible' => [
        [
          [':input[name="field_contact_category"]' => ['value' => 'bug']],
          [':input[name="field_contact_category"]' => ['value' => 'report']],
        ],
      ],
    ];

    $form['field_contact_url']['widget'][0]['uri']['#states'] = [
      'required' => [
        ':input[name="field_contact_category"]' => ['value' => 'report'],
      ],
    ];

    // Change the text of the submit button to "Submit".
    $form['actions']['submit']['#value'] = $this->t('Submit');

    // Add a submit handler that sends a notification to moderators and shows a
    // success message informing user that their message has been successfully
    // sent.
    $form['actions']['submit']['#submit'][] = static::class . ':submit';
    $form['#validate'][] = static::class . ':validate';

    $form['#attached']['library'][] = 'contact_form/contact-form';

    $form['actions']['#suffix'] = '<span class="d-block mt-2">' . (string) $this->t('Submissions of this form are processed by a contractor of the European Commission.') . '</span>';
    if ($this->moduleHandler->moduleExists('honeypot')) {
      $this->honeypotService->addFormProtection(
        $form,
        $formState,
        ['honeypot', 'time_restriction']
      );
    }
  }

  /**
   * Form submission handler for the contact form.
   */
  public function submit(&$form, FormStateInterface $formState): void {
    $event = new NotificationEvent('contact', $formState->getFormObject()->getEntity());
    $this->eventDispatcher->dispatch($event, ContactFormEvents::CONTACT_FORM_EVENT);

    if ($event->isSuccessful()) {
      $this->messenger->addStatus($this->t('Your message has been submitted. Thank you for your feedback.'));
    }
    else {
      $this->messenger->addError($this->t('The message submission failed.'));
      $this->logger->error('Cannot send contact form message.', [
        'message' => $event->getEntity()->toArray(),
      ]);
    }
  }

  /**
   * Validate page url if category is report.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function validate(array &$form, FormStateInterface $formState): void {
    $values = $formState->getValues();
    $uri = $values['field_contact_url'][0]['uri'] ?? '';

    if (!empty($values['field_contact_category']) && $values['field_contact_category'][0]['value'] === 'report' && $uri === '') {
      $formState->setErrorByName('field_contact_url', $this->t('Page URL field is required.'));
      return;
    }

    // The link widget accepts the link module's special "magic" routes
    // (<front>, <none>, <nolink>, <button>) as valid internal links, but none
    // of them point to an actual Portal page. Reject them explicitly.
    if ($uri !== '') {
      try {
        $url = Url::fromUri($uri);
        if ($url->isRouted() && in_array($url->getRouteName(), ['<front>', '<none>', '<nolink>', '<button>'], TRUE)) {
          $formState->setErrorByName('field_contact_url', $this->t('Please enter a valid Portal page URL.'));
        }
      }
      catch (\InvalidArgumentException) {
        // Malformed URIs are already rejected by the link field constraints.
      }
    }
  }

}
