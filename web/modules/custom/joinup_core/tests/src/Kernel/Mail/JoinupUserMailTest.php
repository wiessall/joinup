<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Kernel\Mail;

use Drupal\joinup_notification\MailContentTypeHeader;
use Drupal\user\UserInterface;

/**
 * Tests user password reset email.
 *
 * @group joinup_core
 */
class JoinupUserMailTest extends JoinupMailKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installFromConfigCollection(['user.mail']);
  }

  /**
   * Tests user password reset email.
   */
  public function testUserResetEmail(): void {
    $user = \Drupal::entityTypeManager()->getStorage('user')->load(1);
    \assert($user instanceof UserInterface);
    $result = (bool) _user_mail_notify('password_reset', $user);
    $this->assertTrue($result);

    $this->assertEquals(
      [
        [
          'id' => 'user_password_reset',
          'module' => 'user',
          'key' => 'password_reset',
          'to' => 'admin@example.com',
          'from' => 'joinup@example.com',
          'reply-to' => 'joinup@example.com',
          'langcode' => 'en',
          'send' => TRUE,
          'subject' => 'Please confirm the request of a new password.',
          'body' => static::HTML_VERSION,
          'headers' => [
            'content-transfer-encoding' => '8Bit',
            'content-type' => MailContentTypeHeader::TextHtml->value,
            'from' => 'Interoperable Europe Portal <joinup@example.com>',
            'mime-version' => '1.0',
            'reply-to' => 'joinup@example.com',
            'return-path' => 'joinup@example.com',
            'sender' => 'joinup@example.com',
            'x-mailer' => 'Drupal',
          ],
          'plain' => static::PLAINTEXT_VERSION,
        ],
      ],
      $this->getCatchedEmailsNormalized()
    );
  }

  /**
   * Expected HTML version of the password reset email.
   *
   * @const string
   */
  const HTML_VERSION = <<<'EOS'
<html>
<head>
<style>
table tr td {
  font-family: Arial;
  font-size: 12px;
}
</style>
</head>
<body>
<div>
  <table style="width: 800px;" cellpadding="0" cellspacing="0">
    <tr>
      <td>
        <div style="padding: 0px 0px 0px 0px;">
          <p>Dear AdminJane,</p>
<p>A new password has been requested for the account admin.</p>
<p>To confirm the request, please click <a href="http://localhost/user/reset/1/1690449287/poghcpHrsWKpNm05j_gcSjv43VJ7actKegl6F-mz384">http://localhost/user/reset/1/1690449287/poghcpHrsWKpNm05j_gcSjv43VJ7actKegl6F-mz384</a>.</p>
<p>If you didn't ask for a new password, please disregard this message.</p>
<p>Kind regards,</p>
<p>The Portal Support Team</p>


        </div>
      </td>
    </tr>
  </table>
</div>
</body>
</html>
EOS;

  /**
   * Expected palin text version of the password reset email.
   *
   * @const string
   */
  const PLAINTEXT_VERSION = <<<'EOS'
Dear AdminJane,

A new password has been requested for the account admin.

To confirm the request, please click
http://localhost/user/reset/1/1690449287/poghcpHrsWKpNm05j_gcSjv43VJ7actKegl6F-mz384.

If you didn't ask for a new password, please disregard this message.

Kind regards,

The Portal Support Team
EOS;

}
