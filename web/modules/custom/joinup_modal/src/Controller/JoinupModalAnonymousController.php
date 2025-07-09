<?php

declare(strict_types=1);

namespace Drupal\joinup_modal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;

/**
 * Controller for displaying a joinup modal anonymous page.
 */
class JoinupModalAnonymousController extends ControllerBase {

  use RedirectDestinationTrait;

  /**
   * Returns a render-able array for a joinup modal anonymous page.
   *
   * @param string $type
   *   Modal type.
   *
   * @return array
   *   The "joinup_modal_anonymous" render array.
   */
  public function render(string $type): array {
    $login = Url::fromRoute('cas.login', [], [
      'query' => $this->getDestinationArray(),
    ]);

    // Add a random query parameter to the link force the browser to
    // recalculate the 302 redirect to the external service.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-9903.
    // @todo Remove these lines in ISAICP-9990.
    $query = $login->getOption('query');
    $query['t'] = rand(1000, 9999);
    $login->setOption('query', $query);

    return [
      '#theme' => "joinup_modal_anonymous__$type",
      '#title' => $this->t('You are not signed in'),
      '#login' => new Link('Sign in', $login),
      // Replicate logic of "Create an account" from navigation, it points
      // to the EU login page where user can click on "Create an account".
      '#register' => new Link('Register', $login),
      '#cache' => [
        'contexts' => ['url'],
      ],
    ];
  }

}
