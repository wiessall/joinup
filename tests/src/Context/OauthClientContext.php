<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;

/**
 * Behat step definitions for testing OAuth client requests.
 */
class OauthClientContext extends RawDrupalContext {

  use EntityTrait;
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
      if (empty($values['user']) || !$user = user_load_by_name($values['user'])) {
        throw new \InvalidArgumentException('User is required.');
      }

      $values['uid'] = $user->id();
      $values['user'] = $user->id();

      $request = $storage->create($values);
      $request->save();

      $this->entities['oauth_client_request'][$request->id()] = $request;
    }
  }

  /**
   * Deletes an OAuth client request by label.
   *
   * @param string $label
   *   The label of the OAuth client request to delete.
   *
   * @When I delete the :label oauth client request
   *
   * @throws \RuntimeException
   *   Thrown when the OAuth client request is not found.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when the entity could not be deleted.
   */
  public function deleteOauthClientRequestByLabel(string $label): void {
    $request = $this->getEntityByLabel('oauth_client_request', $label);
    $request->skip_notification = TRUE;
    $request->delete();
  }

  /**
   * Visits the edit or delete page of an OAuth client request by label.
   *
   * @param string $action
   *   The action. Either 'edit' or 'delete'.
   * @param string $label
   *   The label of the OAuth client request.
   *
   * @When I visit the :action page of the :label oauth client request
   * @When I go to the :action page of the :label oauth client request
   *
   * @throws \InvalidArgumentException
   *   Thrown when an invalid action is passed.
   * @throws \RuntimeException
   *   Thrown when the OAuth client request is not found.
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   Thrown when the entity URL cannot be generated.
   */
  public function visitOauthClientRequestPage(string $action, string $label): void {
    if (!in_array($action, ['edit', 'delete'])) {
      throw new \InvalidArgumentException('Only "edit" and "delete" actions are allowed.');
    }

    $request = $this->getEntityByLabel('oauth_client_request', $label);
    $path = $request->toUrl("{$action}-form")->getInternalPath();
    $this->visitPath($path);
  }

}
