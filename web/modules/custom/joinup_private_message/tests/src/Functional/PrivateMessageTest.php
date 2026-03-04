<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_private_message\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\private_message\Entity\PrivateMessage;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the Private Messages.
 */
#[Group('joinup_private_message')]
class PrivateMessageTest extends BrowserTestBase {
  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to install.
   *
   * @var array
   */
  protected static $modules = ['joinup_private_message'];

  /**
   * The second User used for the test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->user = $this->drupalCreateUser([
      'use private messaging system',
      'access user profiles',
      'delete any private message',
    ]);
  }

  /**
   * Tests that the canonical private message route redirects correctly.
   */
  public function testPrivateMessageCanonicalRoute(): void {
    // Create a private message.
    $message = PrivateMessage::create([
      'owner' => $this->user->id(),
    ]);
    $message->save();

    $this->drupalLogin($this->user);
    $this->drupalGet(Url::fromRoute('entity.private_message.canonical', [
      'private_message' => $message->id(),
    ]));
    $this->assertSession()->addressEquals('user/messages');
  }

  /**
   * Tests that the delete private message page has no errors.
   */
  public function testDeleteMessage(): void {
    // Create a private message.
    $message = PrivateMessage::create([
      'owner' => $this->user->id(),
    ]);
    $message->save();

    $this->drupalLogin($this->user);
    $this->drupalGet('/user/messages/message/' . $message->id() . '/delete');
    $this->assertSession()->statusCodeEquals(200);
  }

}
