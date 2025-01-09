<?php

declare(strict_types=1);

namespace Drupal\joinup_notification;

/**
 * Define events for the joinup notification module.
 */
final class NotificationEvents {

  /**
   * An event for sending notifications on node creation.
   *
   * @Event
   *
   * @var string
   */
  const string NODE_CREATE = 'joinup_notification.node.create';

  /**
   * An event for sending notifications on node update.
   *
   * @Event
   *
   * @var string
   */
  const string NODE_UPDATE = 'joinup_notification.node.update';

  /**
   * An event for sending notifications on node deletion.
   *
   * @Event
   *
   * @var string
   */
  const string NODE_DELETE = 'joinup_notification.node.delete';

  /**
   * An event that sends notifications on comment CRUD operations.
   *
   * @Event
   *
   * @var string
   */
  const string COMMENT_CRUD = 'joinup_notification.comment.notify';

  /**
   * An event that sends notifications when a membership state is changed.
   *
   * @Event
   *
   * @var string
   */
  const string OG_MEMBERSHIP_MANAGEMENT = 'joinup_notification.og_membership.management';

  /**
   * Event when solution is completely archived along with its sub-items.
   *
   * @Event
   *
   * @var string
   */
  const string GROUP_ENTITY_ARCHIVE_COMPLETED = 'joinup_notification.group.archive_completed.notify';

}
