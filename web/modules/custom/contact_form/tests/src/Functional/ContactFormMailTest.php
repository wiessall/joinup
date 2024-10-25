<?php

declare(strict_types=1);

namespace Drupal\Tests\contact_form\Functional;

use Drupal\Tests\joinup_core\Functional\Mail\JoinupMailFunctionalTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests emails sent on contact form submission including message digest email.
 *
 * @contact_form
 */
class ContactFormMailTest extends JoinupMailFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'contact_form',
    'slick',
    'oe_bootstrap_theme_helper',
    'joinup_bookmark',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->installFromConfigCollection([
      'contact_form.settings',
      'filter.format.basic_html',
      'filter.format.content_editor',
      'message.template.contact_form_submission',
      'field.storage.message.field_contact_*',
      'field.field.message.contact_form_submission',
      'core.entity_form_display.message.contact_form_submission',
      'core.entity_view_display.message.contact_form_submission.*',
    ]);

    Role::load(RoleInterface::ANONYMOUS_ID)
      ->grantPermission('access content')
      ->grantPermission('use text format basic_html')
      ->save();
  }

  /**
   * Tests emails sent after contact form submission.
   *
   * @param bool $with_copy
   *   Whether test user asks for the copy of their message or not.
   *
   * @dataProvider providerTestEmailSending
   */
  public function testEmailSending(bool $with_copy): void {
    $this->drupalGet('contact');
    $this->assertSession()->statusCodeEquals(200);
    $this->submitForm(
      [
        'field_contact_first_name[0][value]' => 'Jane',
        'field_contact_last_name[0][value]' => 'Doe',
        'field_contact_email[0][value]' => 'jane.doe@example.com',
        'field_contact_category' => 'usage',
        'field_contact_subject[0][value]' => 'Question subject',
        'field_contact_message[0][value]' => 'Question about Joinup',
        'field_contact_copy[value]' => $with_copy,
      ],
      'Submit'
    );

    $this->assertSession()->statusCodeEquals(200);
    $this->assertEquals(
      [
        [
          'id' => 'message_notify_contact_form_submission',
          'module' => 'message_notify',
          'key' => 'contact_form_submission',
          'to' => 'DIGIT-JOINUP-SUPPORT-EXT-FORWARD@ec.europa.eu',
          'from' => 'digit-joinup@ec.europa.eu',
          'reply-to' => NULL,
          'langcode' => 'en',
          'send' => TRUE,
          'subject' => 'Joinup - Contact form submission',
          'body' => self::NOTIFICATION_HTML,
          'headers' => [
            'content-transfer-encoding' => '8Bit',
            'content-type' => 'text/html',
            'from' => 'Joinup <joinup@example.com>',
            'mime-version' => '1.0',
            'return-path' => 'joinup@example.com',
            'sender' => 'joinup@example.com',
            'x-mailer' => 'Drupal',
          ],
          'plain' => self::NOTIFICATION_PLAIN,
        ],
        [
          'id' => 'contact_form_contact_form_sender',
          'module' => 'contact_form',
          'key' => 'contact_form_sender',
          'to' => 'jane.doe@example.com',
          'from' => 'joinup@example.com',
          'reply-to' => NULL,
          'langcode' => 'en',
          'send' => TRUE,
          'subject' => 'Joinup: Contact form notification',
          'body' => $with_copy ? self::COPY_HTML : self::WITHOUT_COPY_HTML,
          'headers' => [
            'content-transfer-encoding' => '8Bit',
            'content-type' => 'text/html',
            'from' => 'Joinup <joinup@example.com>',
            'mime-version' => '1.0',
            'return-path' => 'joinup@example.com',
            'sender' => 'joinup@example.com',
            'x-mailer' => 'Drupal',
          ],
          'plain' => $with_copy ? self::COPY_PLAIN : self::WITHOUT_COPY_PLAIN,
        ],
      ],
      $this->getCatchedEmailsNormalized()
    );
  }

  /**
   * Data provider for ::testEmailSending.
   */
  public function providerTestEmailSending(): array {
    return [
      'Withouth copy' => [FALSE],
      'With copy' => [TRUE],
    ];
  }

  const NOTIFICATION_HTML = <<<EOS
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
          <div class="field--name-field-contact-first-name contact-form-submission__field-contact-first-name">
<div class="field__label fw-bold">
      First name    </div>
<div class="field__item">Jane</div>
</div>
<div class="field--name-field-contact-last-name contact-form-submission__field-contact-last-name">
<div class="field__label fw-bold">
      Last name    </div>
<div class="field__item">Doe</div>
</div>
<div class="field--name-field-contact-email contact-form-submission__field-contact-email">
<div class="field__label fw-bold">
      E-mail address    </div>
<div class="field__item">jane.doe@example.com</div>
</div>
<div class="field--name-field-contact-category contact-form-submission__field-contact-category">
<div class="field__label fw-bold">
      Category    </div>
<div class="field__item">Question on usage</div>
</div>
<div class="field--name-field-contact-subject contact-form-submission__field-contact-subject">
<div class="field__label fw-bold">
      Subject    </div>
<div class="field__item">Question subject</div>
</div>
<div class="field--name-field-contact-message contact-form-submission__field-contact-message">
<div class="field__label fw-bold">
      Message    </div>
<div class="field__item">Question about Joinup</div>
</div>


        </div>
      </td>
    </tr>
  </table>
</div>
</body>
</html>
EOS;

  const NOTIFICATION_PLAIN = <<<EOS
First name \nJane \n
 Last name \nDoe \n
 E-mail address \njane.doe@example.com \n
 Category \nQuestion on usage \n
 Subject \nQuestion subject \n
 Message \nQuestion about Joinup
EOS;

  const COPY_HTML = <<<'EOS'
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
          <p>Dear Jane Doe</p>
<p>Thank you for submitting your message to the Portal Support.</p>
<p>This is the message you submitted through the online form:</p>
<p>Subject: <em class="placeholder">Question subject</em></p>
<p>Content:</p>
<blockquote><p>Question about Joinup</p>
</blockquote>
<p>Thank you for your interest, we will reply as soon as possible.</p>
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

  const COPY_PLAIN = <<<EOS
Dear Jane Doe

Thank you for submitting your message to the Portal Support.

This is the message you submitted through the online form:

Subject: _Question subject_

Content:

> Question about Joinup

Thank you for your interest, we will reply as soon as possible.

Kind regards,

The Portal Support Team
EOS;


  const WITHOUT_COPY_HTML = <<<'EOS'
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
          <p>Dear Jane Doe</p>
<p>Thank you for submitting your message to the Portal Support.</p>
<p>We will reply as soon as possible.</p>
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

  const WITHOUT_COPY_PLAIN = <<<EOS
Dear Jane Doe

Thank you for submitting your message to the Portal Support.

We will reply as soon as possible.

Kind regards,

The Portal Support Team
EOS;

}
