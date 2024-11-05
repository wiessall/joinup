<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\private_message\Entity\PrivateMessage;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for Joinup private message routes.
 */
class PrivateMessageController extends ControllerBase {

  /**
   * Redirect users that were heading to a private message page.
   *
   * We don't want to show a private message detail page, so
   * the user is redirected to generic list page.
   *
   * @param \Drupal\private_message\Entity\PrivateMessage $private_message
   *   The private message where the user was heading to.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   */
  public function redirectCanonical(PrivateMessage $private_message): RedirectResponse {
    return $this->redirect('private_message.private_message_page');
  }

}
