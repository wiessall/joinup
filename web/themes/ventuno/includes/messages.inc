<?php

/**
 * @file
 * Messages specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_bundle_class\AbstractInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\message\MessageInterface;
use Drupal\user\Entity\User;

/**
 * Implements hook_preprocess_symfony_mailer_lite_email().
 */
function ventuno_preprocess_symfony_mailer_lite_email(array &$variables): void {
  $variables['site_url'] = \Drupal::request()->getSchemeAndHttpHost();
}

/**
 * Implements hook_preprocess_message_digest().
 */
function ventuno_preprocess_message_digest(array &$variables): void {
  $variables['site_url'] = \Drupal::request()->getSchemeAndHttpHost();

  // Retrieve the recipient from the message list. The recipient is the owner of
  // the message.
  $user = array_reduce($variables['messages'], function (AccountInterface $user, array $message_render_array): AccountInterface {
    // Continue to the next message if we already found a user, or cannot find a
    // message.
    $message = $message_render_array['#message'] ?? NULL;
    if ($user->isAuthenticated() || !$message instanceof MessageInterface) {
      return $user;
    }

    return $message->getOwner();
  }, User::getAnonymousUser());

  $variables['receiver_name'] = $user->isAuthenticated() ? $user->field_user_first_name->value : t('Anonymous');
  $variables['receiver_id'] = $user->id();

  $current_time = \Drupal::time()->getCurrentTime();
  $variables['issue_date'] = date('d/m/Y', $current_time);

  $variables['frequency'] = Unicode::ucfirst($user->field_user_frequency->value);
}

/**
 * Implements hook_preprocess_HOOK().
 *
 * Adapts the output of the collection header in collection content subscription
 * digest messages:
 * - Changes the link to the collection page to an absolute URL so this can be
 *   opened in email messages.
 */
function ventuno_preprocess_rdf_entity__digest_message_header(array &$variables): void {
  $variables['url'] = $variables['rdf_entity']->toUrl('canonical', ['absolute' => TRUE])->toString();
}

/**
 * Implements hook_preprocess_HOOK() for node__digest_message.
 *
 * Adds abstract or (shortened) description field to digest message output
 * of nodes.
 */
function ventuno_preprocess_node__digest_message(&$variables): void {
  $node = $variables['node'];

  // Use abstract if present and non-empty.
  if (
    $node instanceof AbstractInterface &&
    !$node->get($node->getAbstractField())->isEmpty() &&
    isset($variables['content'][$node->getAbstractField()])
  ) {
    $variables['content']['body'] = $variables['content'][$node->getAbstractField()];
  }
  // Fall back to description if it exists.
  elseif (
    $node instanceof DescriptionInterface &&
    isset($variables['content'][$node->getDescriptionField()])
  ) {
    $variables['content']['body'] = $variables['content'][$node->getDescriptionField()];
  }
}
