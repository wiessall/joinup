<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\TestingEntitiesTrait;

/**
 * Behat step definitions for testing OAuth client requests.
 */
class OauthClientContext extends RawDrupalContext {

  use TestingEntitiesTrait;

  /**
   * Creates OAuth client requests from table data.
   *
   * @param \Behat\Gherkin\Node\TableNode $requestsTable
   *   The OAuth client request data table.
   *
   * @Given oauth client requests:
   */
  public function givenOauthClientRequests(TableNode $requestsTable): void {
    $storage = \Drupal::entityTypeManager()->getStorage('oauth_client_request');
    foreach ($requestsTable->getColumnsHash() as $values) {
      if (isset($values['user'])) {
        $user = user_load_by_name($values['user']);
        $values['uid'] = $user ? $user->id() : 0;
        unset($values['user']);
      }

      $request = $storage->create($values);
      $request->save();

      $this->entities['oauth_client_request'][$request->id()] = $request;
    }
  }

}