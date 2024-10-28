<?php

declare(strict_types=1);

namespace Drupal\joinup_eulogin\Event\Subscriber;

use Drupal\Component\Utility\Random;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Event\CasPostValidateEvent;
use Drupal\cas\Event\CasPreLoginEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Event\CasPreValidateEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\externalauth\Authmap;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Listens to CAS events.
 */
class JoinupEuLoginCasEventsSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The module's settings.
   */
  protected ImmutableConfig $settings;

  /**
   * The CAS settings.
   */
  protected ImmutableConfig $casSettings;

  /**
   * The current request.
   */
  protected Request $currentRequest;

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\user\UserDataInterface $userData
   *   The user data service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\externalauth\Authmap $authmap
   *   The authmap service.
   */
  public function __construct(
    ConfigFactoryInterface $configFactory,
    protected UserDataInterface $userData,
    protected EntityTypeManagerInterface $entityTypeManager,
    RequestStack $requestStack,
    protected MessengerInterface $messenger,
    protected StateInterface $state,
    protected Authmap $authmap,
  ) {
    $this->settings = $configFactory->get('joinup_eulogin.settings');
    $this->casSettings = $configFactory->get('cas.settings');
    $this->currentRequest = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CasHelper::EVENT_PRE_VALIDATE => 'alterValidationUrl',
      CasHelper::EVENT_POST_VALIDATE => [
        ['prepareAttributes'],
        ['blockedUserMessage'],
      ],
      CasHelper::EVENT_PRE_REGISTER => [
        ['markAsNewAccountRegistration'],
        ['handleDrupalUsernameCollision'],
        ['preventRegisterEmailCollision'],
      ],
      // This runs just before CasAttributesSubscriber::onPreLogin().
      // @see \Drupal\cas_attributes\Subscriber\CasAttributesSubscriber::onPreLogin()
      CasHelper::EVENT_PRE_LOGIN => ['handlePotentialMailCollision', 21],
      CasHelper::EVENT_POST_LOGIN => [
        ['storeAttributes'],
        ['setRedirectAndStatusOnNewAccount', -20],
      ],
    ];
  }

  /**
   * Alters the validation path and query string.
   *
   * EU Login uses a different ticket validation path comparing to the standard
   * CAS 3.0 specifications. Also, it adds additional query parameters to the
   * ticket validation URL.
   *
   * @param \Drupal\cas\Event\CasPreValidateEvent $event
   *   The CAS pre-validate event object.
   */
  public function alterValidationUrl(CasPreValidateEvent $event): void {
    $parameters = [
      'assuranceLevel' => $this->settings->get('ticket_validation.assurance_level'),
      'ticketTypes' => implode(',', $this->settings->get('ticket_validation.ticket_types')),
    ];
    if ($this->settings->get('ticket_validation.user_details')) {
      $parameters['userDetails'] = 'true';
    }

    $event->setValidationPath($this->settings->get('ticket_validation.path'));
    $event->addParameters($parameters);
  }

  /**
   * Stores the EU Login attributes in the CAS property bag.
   *
   * @param \Drupal\cas\Event\CasPostValidateEvent $event
   *   The CAS post-validate event object.
   */
  public function prepareAttributes(CasPostValidateEvent $event): void {
    $xml = new \SimpleXMLElement($event->getResponseData());
    foreach ($xml->xpath('//cas:authenticationSuccess/*') as $element) {
      $event->getCasPropertyBag()->setAttribute($element->getName(), $element->__toString());
    };
  }

  /**
   * Displays a message to blocked users.
   *
   * @param \Drupal\cas\Event\CasPostValidateEvent $event
   *   The CAS post-validate event object.
   */
  public function blockedUserMessage(CasPostValidateEvent $event): void {
    $uid = $this->authmap->getUid($event->getCasPropertyBag()->getUsername(), 'cas');
    if ($uid && ($user = $this->entityTypeManager->getStorage('user')->load($uid)) && $user->isBlocked()) {
      $this->messenger->addError('You cannot sign in yet. Your Interoperable Europe Portal account is currently being verified. You will be notified by email once it is activated.');
    }
  }

  /**
   * Marks this login as being preceded by account registration.
   *
   * We use this flag later, in ::redirectOnNewAccount().
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The CAS pre-register event.
   *
   * @see self::redirectOnNewAccount()
   */
  public function markAsNewAccountRegistration(CasPreRegisterEvent $event): void {
    $event->getCasPropertyBag()->setAttribute('newAccountRegistered', TRUE);
  }

  /**
   * Assigns a new name to the user being registered if a local user exists.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The CAS pre user register event object.
   */
  public function handleDrupalUsernameCollision(CasPreRegisterEvent $event): void {
    if (!$this->casSettings->get('user_accounts.auto_register')) {
      return;
    }

    $newName = $name = $event->getDrupalUsername();
    while (user_load_by_name($newName)) {
      $newName = $this->buildUserName($name);
    }

    $event->setDrupalUsername($newName);
  }

  /**
   * Prevents registration of a CAS user when their email already exists.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The CAS pre-register event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the storage handler couldn't be loaded.
   */
  public function preventRegisterEmailCollision(CasPreRegisterEvent $event): void {
    $mail = $event->getPropertyValues()['mail'] ?? NULL;
    if (!$mail) {
      throw new \InvalidArgumentException("Unexpected empty 'mail' property value.");
    }

    $sameMailAccounts = $this->entityTypeManager->getStorage('user')->loadByProperties([
      'mail' => $mail,
    ]);

    if ($sameMailAccounts) {
      $event->cancelAutomaticRegistration($this->t('While trying to register your account with Interoperable Europe Portal, we found that your EU Login email address %mail is already in use on our site. Either change your EU Login email address or contact the <a href=":contact">Interoperable Europe Portal support</a> if you feel that something is wrong.', [
        '%mail' => $mail,
        ':contact' => Url::fromRoute('contact_form.contact_page')->toString(),
      ]))->stopPropagation();
    }
  }

  /**
   * Handles the case when a use changes its mail upstream with an existing one.
   *
   * When users are changing their EU Login account email, upstream, it might
   * happen that another user is registered with the same email address on
   * Interoperable Europe Portal. This would lead to duplicate emails on Interoperable Europe Portal which is unacceptable
   * with respect to data integrity. Note that Drupal allows email duplicates at
   * API level but not when using the UI. However, Interoperable Europe Portal is enforcing email
   * uniqueness across users. As this edge case is very rare and unlikely, we
   * only throw an exception.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   The CAS pre-login event.
   *
   * @throws \Exception
   *   When a user changes its EU Login email to a value that is already taken
   *   by other Interoperable Europe Portal user.
   */
  public function handlePotentialMailCollision(CasPreLoginEvent $event): void {
    $account = $event->getAccount();
    $euloginEmail = $event->getCasPropertyBag()->getAttribute('email');

    // A new email has been configured upstream, on the EU Login account. If the
    // account was linked manually, there is a chance that the email stored
    // locally does not share the same case sensitivity as the upstream account.
    // In these cases, allow to proceed so that the local account can retrieve
    // the correct case sensitivity email.
    if (strtolower($account->getEmail()) !== strtolower($euloginEmail)) {
      if (user_load_by_mail($euloginEmail)) {
        $event->cancelLogin($this->t("You've recently changed your EU Login account email but that email is already used in Interoperable Europe Portal by another user. You cannot login until, either you change your EU Login email or you <a href=':url'>contact support</a> to fix the issue.", [
          ':url' => Url::fromRoute('contact_form.contact_page')->setAbsolute()->toString(),
        ]));
      }
    }
  }

  /**
   * Stores the EU Login attributes in user data storage.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The CAS post-login event object.
   */
  public function storeAttributes(CasPostLoginEvent $event): void {
    $this->userData->set('joinup_eulogin', $event->getAccount()->id(), 'attributes', $event->getCasPropertyBag()->getAttributes());
  }

  /**
   * Sets a redirect and a success status message after registering the account.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The CAS post-login event object.
   */
  public function setRedirectAndStatusOnNewAccount(CasPostLoginEvent $event): void {
    if ($event->getCasPropertyBag()->hasAttribute('newAccountRegistered')) {
      $url = $event->getAccount()->toUrl('edit-form');
      $this->currentRequest->query->set('destination', $url->toString());
      $this->messenger->addStatus('Fill in the fields below to let the Interoperable Europe Portal community learn more about you!');
    }
  }

  /**
   * Builds a new username starting from the original name.
   *
   * @param string $name
   *   The original name passed by the event dispatcher.
   *
   * @return string
   *   A new name that pretends to be unique.
   */
  protected function buildUserName(string $name): string {
    if (mb_strlen($name) > UserInterface::USERNAME_MAX_LENGTH - 7) {
      // In case the original name is too long, make some room for the suffix.
      $name = substr($name, 0, UserInterface::USERNAME_MAX_LENGTH - 7);
    }
    return $name . '_' . (new Random())->name(6, TRUE);
  }

}
