<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Core\Url;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\MailCollectorTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og\OgMembershipInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Behat step definitions for interacting with groups.
 */
class JoinupGroupContext extends RawDrupalContext {

  use EntityTrait;
  use MailCollectorTrait;
  use TestingEntitiesTrait;

  /**
   * Navigates to the members page of the given group.
   *
   * @param string $label
   *   The name of the group.
   *
   * @When I go to the members page of :label
   * @When I am on the members page of :label
   */
  public function visitMembersPage(string $label): void {
    $group = $this->getEntityByLabel('node', $label);
    $url = Url::fromRoute("entity.{$group->getEntityTypeId()}.member_overview", [
      $group->getEntityTypeId() => $group->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Navigates to the membership permissions table of the given group.
   *
   * @param string $label
   *   The name of the group.
   *
   * @When I go to the member(ship) permissions table of :label
   */
  public function visitMembershipPermissionsTable(string $label): void {
    $group = $this->getEntityByLabel('node', $label);
    $url = Url::fromRoute('joinup_group.membership_permissions_info', [
      'rdf_entity' => $group->id(),
    ]);
    $this->visitPath($url->toString());
  }

  /**
   * Navigates to the discussions page of a collection/solution.
   *
   * @param string $label
   *   The label of the group for which to visit the discussion page.
   *
   * @Given I go to the discussions page of :label
   */
  public function visitDiscussionPage(string $label): void {
    $group = $this->getEntityByLabel('node', $label);

    $url = Url::fromRoute('view.discussions.overview', [
      'rdf_entity' => $group->id(),
    ]);

    $this->visitPath($url->toString());
  }

  /**
   * Navigates to the manage content page of a collection/solution.
   *
   * @param string $label
   *   The label of the group for which to visit the about page.
   *
   * @Given I go to the manage content page of :label
   */
  public function visitManageContentPage(string $label): void {
    $group = $this->getEntityByLabel('node', $label);

    $url = Url::fromRoute('view.group_content_management.manage', [
      'rdf_entity' => $group->id(),
    ]);

    $this->visitPath($url->toString());
  }

  /**
   * Checks if the given node belongs to the given group.
   *
   * If there are multiple nodes or groups with the same name, then only
   * the first one is checked.
   *
   * @param string $group_label
   *   The name of the collection or solution to check.
   * @param string $group_type
   *   The type of the group.
   * @param string $content_title
   *   The title of the node to check.
   *
   * @throws \Exception
   *   Thrown when a node with the given title doesn't exist.
   *
   * @Then the :group_label :group_type should have a custom page titled :group_title
   * @Then the :group_label :group_type should have a community content titled :group_title
   */
  public function assertNodeOgMembership(string $group_label, string $group_type, string $content_title): void {
    $group = $this->getEntityByLabel('node', $group_label, $group_type);
    $node = $this->getEntityByLabel('node', $content_title);
    if ($node->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)->target_id !== $group->id()) {
      throw new \Exception("The node '$content_title' is not associated with collection '{$group->label()}'.");
    }
  }

  /**
   * Asserts that a group menu link points to a resource outside the group.
   *
   * @param string $link_label
   *   The link text.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   The the link is not found in page.
   *
   * @Then the link :link_label points outside group
   */
  public function assertLinkPointsOutsideGroup(string $link_label): void {
    $session = $this->getSession();
    $page = $session->getPage();
    if (!$link = $page->findLink($link_label)) {
      throw new ElementNotFoundException($session, 'link', $link_label, 'label');
    }
    if (!$link->hasClass('group-menu-link-external')) {
      throw new ExpectationFailedException("Link '{$link_label}' should point to a resource outside the current group but it doesn't.");
    };
  }

  /**
   * Checks that a cookie is set that tracks which group a user wants to join.
   *
   * An anonymous user can join a group only after they sign in or register. A
   * cookie is set which will track which group the user wanted to join.
   *
   * @param string $label
   *   The label of the group the user wants to join.
   *
   * @Then a cookie should be set that allows me to join :label after authenticating
   */
  public function assertAnonGroupJoinTrackingCookiePresent(string $label): void {
    $cookie = $this->getSession()->getCookie('join_group');
    $entity = $this->getEntityByLabel('node', $label);
    Assert::assertEquals($entity->id(), $cookie, "A cookie was expected to track that an anonymous user wants to join the '$label' group.");
  }

  /**
   * Checks that no cookie is set that tracks which group a user wants to join.
   *
   * @Then the cookie that tracks which group I want to join should not be set
   */
  public function assertNoAnonGroupJoinTrackingCookiePresent(): void {
    Assert::assertEmpty($this->getSession()->getCookie('join_group'), 'No cookie should be set that tracks which group an anonymous user wants to join.');
  }

  /**
   * Checks the number of members in a given group.
   *
   * In OG parlance a group member can be any kind of entity, but this only
   * checks which users are members of the group.
   *
   * @param string $label
   *   The name of the group to check.
   * @param string $type
   *   The group type, either 'collection' or 'solution'.
   * @param int $number
   *   The expected number of members in the group.
   * @param string $membership_state
   *   The state of the membership. Can be 'active', 'pending' or 'blocked'.
   *
   * @throws \Exception
   *   Thrown when the number of members does not not match the expectation.
   *
   * @Then the :label :type should have :number :membership_state member(s)
   */
  public function assertMemberCount(string $label, string $type, int $number, string $membership_state): void {
    $states = [
      OgMembershipInterface::STATE_ACTIVE,
      OgMembershipInterface::STATE_PENDING,
      OgMembershipInterface::STATE_BLOCKED,
    ];

    if (!in_array($membership_state, $states)) {
      throw new \Exception("Invalid membership state '{$membership_state}' found.");
    }

    /** @var \Drupal\joinup_group\Entity\GroupInterface $entity */
    $entity = $this->getEntityByLabel('node', $label, $type);
    $actual = $entity->getMemberCount([$membership_state]);

    if ($actual != $number) {
      throw new \Exception("Wrong number of {$membership_state} members. Expected number: $number, actual number: $actual.");
    }
  }

  /**
   * Navigates to the announcements page of a given group.
   *
   * @param string $group_label
   *   The label of the group where the announcement belongs.
   * @param string $group_type
   *   The group bundle.
   *
   * @When I go to the announcements page of :group_label :group_type
   */
  public function visitGroupAnnouncementsPage(string $group_label, string $group_type): void {
    assert(in_array($group_type, ['collection', 'solution']));
    $group = $this->getEntityByLabel('node', $group_label, $group_type);
    assert($group instanceof GroupInterface);
    $url = Url::fromRoute('view.announcements.group', [
      'node' => $group->id(),
    ])->toString();
    $this->visitPath($url);
  }

  /**
   * Navigates to the edit page of a given announcement.
   *
   * @param string $subject
   *   The announcement subject.
   * @param string $group_label
   *   The label of the group where the announcement belongs.
   * @param string $group_bundle
   *   The group bundle.
   *
   * @When I go to the :subject announcement of :group_label :group_type
   */
  public function visitAnnouncementEditForm(string $subject, string $group_label, string $group_bundle): void {
    assert(in_array($group_bundle, ['collection', 'solution']));
    $announcement = $this->getAnnouncementBySubject($subject);
    $group = $this->getEntityByLabel('node', $group_label, $group_bundle);
    $url = Url::fromRoute('entity.node.announcement_edit', [
      'node' => $group->id(),
      'announcement' => $announcement->id(),
    ])->toString();
    $this->visitPath($url);
  }

  /**
   * Deletes a specific announcement.
   *
   * @param string $subject
   *   The announcement subject.
   *
   * @Then I delete the :subject announcement
   */
  public function deleteAnnouncement(string $subject): void {
    $this->getAnnouncementBySubject($subject)->delete();
  }

  /**
   * Creates a set of announcements.
   *
   * Table format:
   * @code
   * | group             | subject                          | body       | state    | author | created   |
   * | Art Collection    | Come and see what we've achieved | Content... | draft    | user1  | 25/2/2014 |
   * | The Only Solution | Heavy Metal Treatment            | Text...    | approved | user3  | 1/4/2020  |                | http://mashable.com/category/cats/     | yes|no | 28-01-1995 12:06 | The domestic cat (Felis catus or Felis silvestris catus) is a small usually furry domesticated carnivorous mammal. | facilitators and authors|members|registered users | no       |      | no         |                   |       | draft|proposed|published|archival request|archived |
   * @encode
   * The group and subject fields are required. If omitted, the state field
   * defaults to draft and the body will be composed of the subject value. The
   * created date/time default to the current time.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The announcement data.
   *
   * @Given the following announcements:
   */
  public function createAnnouncements(TableNode $table): void {
    $columns = [
      'group' => TRUE,
      'subject' => TRUE,
      'body' => FALSE,
      'state' => FALSE,
      'author' => FALSE,
      'created' => FALSE,
    ];
    $keys = $table->getRow(0);
    if ($invalid_columns = array_diff($keys, array_keys($columns))) {
      throw new \InvalidArgumentException('Invalid column(s): ' . implode(', ', $invalid_columns));
    }
    if ($missing_columns = array_diff(array_keys(array_filter($columns)), $keys)) {
      throw new \InvalidArgumentException('Missing columns: ' . implode(', ', $missing_columns));
    }

    $storage = \Drupal::entityTypeManager()->getStorage('message');
    foreach ($table->getColumnsHash() as $row) {
      $values = [
        'template' => 'announcement',
        'state' => 'draft',
        'uid' => 1,
        'body' => "Content of {$row['subject']}",
        'created' => date('j/n/Y'),
      ];
      foreach ($row as $key => $value) {
        if ($key === 'group') {
          $group = $this->getEntityByLabel('node', $value);
          if (!$group instanceof GroupInterface) {
            throw new \InvalidArgumentException("'$value' should be either a collection or a solution");
          }
          $values['group'] = $group;
          continue;
        }
        elseif ($key === 'author') {
          if (!$account = user_load_by_name($value)) {
            throw new \InvalidArgumentException("'$value' should be a valid user name");
          }
          $values['uid'] = $account;
          continue;
        }
        $values[$key] = $value;
      }
      $values['created'] = strtotime($values['created']);
      $announcement = $storage->create($values);
      $announcement->save();
      $this->entities['message'][$announcement->id()] = $announcement;
    }
  }

  /**
   * Runs a delivery queue (send_group_announcements) for a given announcement.
   *
   * @param string $subject
   *   The announcement subject.
   *
   * @Given the delivery queue of :subject announcement is processed
   */
  public function runDeliveryQueueForAnnouncement(string $subject): void {
    $announcement_id = (int) $this->getAnnouncementBySubject($subject)->id();
    /** @var \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager */
    $queue_worker_manager = \Drupal::service('plugin.manager.queue_worker');
    $queue = \Drupal::queue('send_group_announcements');
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $worker */
    $worker = $queue_worker_manager->createInstance('send_group_announcements');
    while ($item = $queue->claimItem()) {
      if ($item->data['announcement'] === $announcement_id) {
        $worker->processItem($item->data);
        $queue->deleteItem($item);
      }
    }
  }

  /**
   * Asserts that an announcement has been delivered to all group members.
   *
   * @param string $subject
   *   The announcement subject.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   *   When the announcement is missing the group.
   *
   * @Then the :subject announcement should have been delivered for each group member
   */
  public function assertDeliveredAnnouncements(string $subject): void {
    $announcement = $this->getAnnouncementBySubject($subject);
    $body = $announcement->getBody();
    $offset = 0;
    $limit = 250;
    $group = $announcement->getGroup();
    while ($member_emails = $group->getAnnouncementRecipients($offset, $limit)) {
      foreach ($member_emails as $member_email) {
        $emails = $this->getEmailsBySubjectAndMail($announcement->getSubject(), $member_email);
        $email = reset($emails);
        Assert::assertSame($member_email, $email['to']);
        Assert::assertSame($subject, $email['subject']);
        Assert::assertStringContainsString($body, (string) $email['body']);
        $offset += $limit;
      }
    }
  }

  /**
   * Returns an announcement entity given its subject.
   *
   * @param string $subject
   *   The announcement subject.
   *
   * @return \Drupal\joinup_group\Entity\AnnouncementInterface
   *   The announcement entity.
   */
  protected function getAnnouncementBySubject(string $subject): AnnouncementInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('message');
    $mids = $storage->getQuery()->accessCheck(FALSE)
      ->condition('template', 'announcement')
      ->condition('subject', $subject)
      ->execute();
    if (!$mids) {
      throw new \InvalidArgumentException("There's no announcement with the '$subject' subject");
    }
    /** @var \Drupal\joinup_group\Entity\AnnouncementInterface $announcement */
    $announcement = $storage->load(reset($mids));
    return $announcement;
  }

  /**
   * Sets a group settings (meta entity) field to the given value.
   *
   * @param string $config
   *   The configuration (field name) of the collection's settings meta entity.
   * @param string $groupLabel
   *   The label of the collection or solution.
   * @param string $group
   *   The type of the group ("collection" or "solution").
   * @param string $value
   *   The new value of the meta entity field. This should be an entity label if
   *   the field is an entity reference field.
   * @param string|null $entityTypeId
   *   The entity type ID (or alias) if the value is an entity reference.
   *
   * @Given the :config setting of the :collectionLabel :group is set to :value
   * @Given the :config setting of the :collectionLabel :group is set to :value :entityTypeId
   */
  public function givenCollectionSetting(
    string $config,
    string $groupLabel,
    string $group,
    string $value,
    ?string $entityTypeId = NULL,
  ): void {
    Assert::assertTrue(in_array($group, ['collection', 'solution']));
    $groupEntity = $this->getEntityByLabel('rdf_entity', $groupLabel, $group);
    Assert::assertInstanceOf(GroupInterface::class, $groupEntity);
    $fieldName = preg_replace('/\s+/', '_', mb_strtolower($config));

    try {
      $groupEntity->getSetting($fieldName);
    }
    catch (\InvalidArgumentException) {
      Assert::fail(sprintf(
        "The '%s' setting does not exist. (Tried to get field name '%s'.)",
        $config,
        $fieldName,
      ));
    }

    // Assume entity reference field and an entity label in the $value variable.
    if (is_string($entityTypeId)) {
      $value = $this->getEntityByLabel(static::translateEntityTypeAlias($entityTypeId), $labelToSearchFor = $value);
      Assert::assertNotEmpty(
        $value,
        sprintf(
          "Cannot find '%s' entity with label '%s'.",
          $entityTypeId,
          $labelToSearchFor
        ),
      );
    }

    MetaEntity::loadOrCreate("{$group}_settings", $groupEntity)
      ->set($fieldName, $value)
      ->save();
  }

}
