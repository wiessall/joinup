<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

use DrupalFinder\DrupalFinder;
use Robo\Exception\AbortTasksException;

/**
 * Reusable code that allows switching Antibot & Honeypot functionality on/off.
 */
trait AntispamTrait {

  use ConfigReadOnlyTrait;

  /**
   * Keeps track on whether the anti-spam tools are enabled.
   */
  protected static bool $antispamEnabled = TRUE;

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
   * Disables the Antibot & Honeypot functionalities before tests run.
   *
   * Normally, this would happen in @BeforeSuite phase but we want to take
   * advantage of a bootstrapped Drupal, as the API calls are faster, thus we
   * need the context to be available. We use static::$antispamEnabled to make
   * sure we're only running this hook once.
   *
   * @BeforeScenario
   */
  public function disableAntispamOnFirstScenario(): void {
    $this->toggleAntispam(FALSE);
  }

  /**
   * Enable the anti-spam functionalities during @antispam tests.
   *
   * @BeforeScenario @antispam&&@api
   */
  public function enableAntispamBeforeScenarioStarts(): void {
    $this->toggleAntispam(TRUE);
  }

  /**
   * Restores the anti-spam functionalities after @antispam tests.
   *
   * @AfterScenario @antispam&&@api
   */
  public function disableAntispamAfterScenarioEnds(): void {
    $this->toggleAntispam(FALSE);
  }

  /**
   * Toggles usage of anti-spam tools.
   *
   * @param bool $enable
   *   Whether to enable or disable anti-spam functionality.
   *
   * @see joinup_core_honeypot_form_protections_alter()
   * @see \Drupal\joinup_core\Config\JoinupConfigOverrider
   */
  protected function toggleAntispam(bool $enable): void {
    if (static::$antispamEnabled === $enable) {
      return;
    }

    \Drupal::state()->set('joinup_core.antispam.enabled', $enable);

    // Clear the cache bin storing cached markup.
    foreach (['page', 'dynamic_page_cache', 'render'] as $bin) {
      \Drupal::cache($bin)->deleteAll();
    }

    static::$antispamEnabled = !static::$antispamEnabled;
  }

  /**
   * Restores the anti-spam tools functionalities after suite.
   *
   * Note that the context is not available, so we have to use external tool,
   * such as Drush.
   *
   * @AfterSuite
   */
  public static function restoreAntispamAfterSuiteEnds(): void {
    if (static::$antispamEnabled) {
      return;
    }

    $drupal_finder = new DrupalFinder();
    if (!$drupal_finder->locateRoot(getcwd())) {
      throw new AbortTasksException('Cannot locate Drupal path.');
    }
    $vendor_dir = $drupal_finder->getVendorDir();

    shell_exec("$vendor_dir/bin/drush state:delete joinup_core.antispam.enabled");
    // Clear the cache bin storing cached markup.
    shell_exec("$vendor_dir/bin/drush eval 'foreach ([\"page\", \"dynamic_page_cache\", \"render\"] as \$bin) \Drupal::cache(\$bin)->deleteAll();'");

    static::$antispamEnabled = TRUE;
  }

}
