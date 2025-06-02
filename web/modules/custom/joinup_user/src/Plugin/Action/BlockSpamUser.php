<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\Plugin\Action\BlockUser;

/**
 * Blocks a spam user.
 */
#[Action(
  id: 'user_block_spam_user_action',
  label: new TranslatableMarkup('Block the selected users: Spam account'),
  type: 'user'
)]
class BlockSpamUser extends BlockUser {

  /**
   * {@inheritdoc}
   */
  public function execute($account = NULL) {
    if ($account !== FALSE) {
      $account->blocked_reason = 'spam';
      $account->block();
      $account->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!in_array(['moderator', 'developer'], $account->getRoles())) {
      return FALSE;
    }
    /** @var \Drupal\user\UserInterface $object */
    $access = $object->status->access('edit', $account, TRUE)
      ->andIf($object->access('update', $account, TRUE));
    return $return_as_object ? $access : $access->isAllowed();
  }

}
