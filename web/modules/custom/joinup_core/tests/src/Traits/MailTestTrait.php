<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Traits;

/**
 * Trait for checking emails sent during testing.
 */
trait MailTestTrait {

  /**
   * Returns the catched emails in a normalized form.
   *
   * @return array[]
   *   List of the catched emails in a normalized form, since the last reset.
   */
  protected function getCatchedEmailsNormalized(): array {
    return array_reduce(
      $this->getCatchedEmails(),
      function (array $carry, array $mail) {
        // Params contains the full user object, we don't need it.
        unset($mail['params']);
        // In case of HTML mails, body might be a MarkupInterface.
        $mail['body'] = (string) $mail['body'];

        // Sort headers and convert header keys to lowercase.
        ksort($mail['headers']);
        $mail['headers'] = array_combine(
          array_map(fn($key) => mb_strtolower($key), array_keys($mail['headers'])),
          $mail['headers'],
        );
        foreach (['body', 'plain'] as $content_key) {
          if (!isset($mail[$content_key]) || !is_string($mail[$content_key])) {
            continue;
          }
          $mail[$content_key] = trim($mail[$content_key]);
        }
        $carry[] = $mail;
        return $carry;
      },
      []
    );
  }

  /**
   * Returns a list of catched emails since the last reset.
   *
   * @return array[]
   *   List of catched emails since the last reset.
   */
  private function getCatchedEmails(): array {
    return \Drupal::state()->get('system.test_mail_collector', []);
  }

  /**
   * Deletes the internal email storage.
   */
  protected function resetStoredEmails(): void {
    \Drupal::state()->delete('system.test_mail_collector');
  }

}
