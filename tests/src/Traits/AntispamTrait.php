<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Hook\BeforeScenario;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\ConfigContext;

/**
 * Reusable code that allows switching Antibot & Honeypot functionality on/off.
 */
trait AntispamTrait {

  /**
   * Checks if the current form is protected by Antibot.
   *
   * @throws \Exception
   *   When the expectancy is not met.
   */
  #[Then('the form is protected by Antibot')]
  public function assertFormIsProtectedByAntibot(): void {
    $session = $this->getSession();

    // Unlock the form by using the Antibot javascript API.
    $session->executeScript('Drupal.antibot.unlockForms();');

    $has_js_assigned_value = (bool) $session->getPage()->find('xpath', '//form[@data-action]//input[@data-drupal-selector="edit-antibot-key" and @name="antibot_key" and string(@value)]');
    if (!$has_js_assigned_value) {
      throw new \Exception("Not an Antibot protected form.");
    }
  }

  /**
   * Checks if the current form is protected by Honeypot.
   *
   * @throws \Exception
   *   When the expectancy is not met.
   */
  #[Then('the form is protected by Honeypot')]
  public function assertFormIsProtectedByHoneypot(): void {
    $session = $this->getSession();

    $has_js_assigned_value = (bool) $session->getPage()->find('xpath', '//form//input[@data-drupal-selector="edit-user-homepage" and @name="user_homepage"]');
    if (!$has_js_assigned_value) {
      throw new \Exception("Not an Honeypot protected form.");
    }
  }

  /**
   * Disable anti-spam for all scenarios unless tagged with @antispam.
   */
  #[BeforeScenario('~@antispam&&@api')]
  public function disableAntispamBeforeScenarioStarts(): void {
    $config_context = $this->getContext(ConfigContext::class);
    $config_context->setTypedConfig('antibot.settings', 'form_ids', json_encode(NULL));
    $config_context->setTypedConfig('honeypot.settings', 'form_settings', json_encode([]));
  }

}
