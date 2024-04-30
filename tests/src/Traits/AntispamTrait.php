<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

/**
 * Reusable code that allows switching Antibot & Honeypot functionality on/off.
 */
trait AntispamTrait {

  use ConfigReadOnlyTrait;

  /**
   * Checks if the current form is protected by Antibot.
   *
   * @throws \Exception
   *   When the expectancy is not met.
   *
   * @Then the form is protected by Antibot
   */
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
   *
   * @Then the form is protected by Honeypot
   */
  public function assertFormIsProtectedByHoneypot(): void {
    $session = $this->getSession();

    $has_js_assigned_value = (bool) $session->getPage()->find('xpath', '//form//input[@data-drupal-selector="edit-user-homepage" and @name="user_homepage"]');
    if (!$has_js_assigned_value) {
      throw new \Exception("Not an Honeypot protected form.");
    }
  }

  /**
   * Enable the anti-spam functionalities during @antispam tests.
   *
   * @BeforeScenario @antispam&&@api
   */
  public function enableAntispamBeforeScenarioStarts(): void {
    \Drupal::state()->set('joinup_test.antispam.enabled', TRUE);
    $this->clearCache();
  }

  /**
   * Restores the anti-spam functionalities after @antispam tests.
   *
   * @AfterScenario @antispam&&@api
   */
  public function disableAntispamAfterScenarioEnds(): void {
    \Drupal::state()->delete('joinup_test.antispam.enabled');
    $this->clearCache();
  }

  /**
   * Clear the cache bins storing cached markup.
   */
  protected function clearCache(): void {
    foreach (['page', 'dynamic_page_cache', 'render'] as $bin) {
      \Drupal::cache($bin)->deleteAll();
    }
  }

}
