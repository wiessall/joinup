<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_group\ContentCreationOptions;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Form for the member permissions information table.
 *
 * Renders a table with a simplified subset of the available permissions for the
 * different roles in a group. This is intended for end users and facilitators
 * to get an overview of the actions they can take in the group.
 *
 * This table is shown in a modal dialog when pressing the "Member permissions"
 * link in the Members page.
 */
class GroupMembershipPermissionsInformationForm extends FormBase {

  /**
   * Builds the table that shows information about member permissions.
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param \Drupal\joinup_group\Entity\GroupInterface|null $node
   *   The group for which the membership permission information is rendered.
   *
   * @return array
   *   A render array containing the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?GroupInterface $node = NULL): array {
    $form['permissions'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Permission'),
        $this->t('Member'),
        $this->t('Author'),
        $this->t('Facilitator'),
        $this->t('Owner'),
      ],
    ];

    foreach ($this->getPermissionInfo($node) as $permission_info) {
      if ($permission_info['applicable'] ?? TRUE) {
        $form['permissions'][] = [
          [
            '#plain_text' => $permission_info['description'],
          ],
          [
            '#plain_text' => $permission_info['permitted']['member'] ? '✓' : '',
          ],
          [
            '#plain_text' => $permission_info['permitted']['author'] ? '✓' : '',
          ],
          [
            '#plain_text' => $permission_info['permitted']['facilitator'] ? '✓' : '',
          ],
          [
            '#plain_text' => $permission_info['permitted']['owner'] ? '✓' : '',
          ],
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
      'close' => [
        '#type' => 'button',
        '#value' => $this->t('Got it'),
        // This button will be used only to close the modal, so it doesn't have
        // any submit callback.
        '#submit' => [],
        '#attributes' => [
          'class' => ['dialog-cancel'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Returns a list of permission information.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group for which the membership permission information is rendered.
   *
   * @return array
   *   A list of permission information.
   */
  protected function getPermissionInfo(GroupInterface $group): array {
    $is_moderated = $group->isModerated();
    $content_creators = $group->getContentCreators();
    $only_authors_can_create = $content_creators === ContentCreationOptions::FACILITATORS_AND_AUTHORS;
    return [
      [
        // A custom description is provided since this is intended for a
        // non-technical audience which is not familiar with Drupal permissions.
        'description' => $this->t('View published content'),
        'permitted' => [
          'member' => TRUE,
          'author' => TRUE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('View unpublished content from other users'),
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Start a discussion'),
        'permitted' => [
          'member' => !$only_authors_can_create,
          'author' => TRUE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Propose content for publication, pending approval'),
        // This only applies for moderated groups. The option is hidden also if
        // only facilitators and authors can create content, since in this case
        // the normal members cannot propose any content.
        'applicable' => $is_moderated && !$only_authors_can_create,
        'permitted' => [
          'member' => TRUE,
          'author' => FALSE,
          'facilitator' => FALSE,
          'owner' => FALSE,
        ],
      ],
      [
        'description' => $this->t('Approve proposed content for publication'),
        'applicable' => $is_moderated && !$only_authors_can_create,
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $is_moderated && !$only_authors_can_create ? $this->t('Publish content without approval') : $this->t('Publish content'),
        'permitted' => [
          'member' => !$is_moderated && !$only_authors_can_create,
          'author' => TRUE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Propose changes to own published content, pending approval'),
        'applicable' => $is_moderated && !$only_authors_can_create,
        'permitted' => [
          'member' => TRUE,
          'author' => FALSE,
          'facilitator' => FALSE,
          'owner' => FALSE,
        ],
      ],
      [
        'description' => $this->t('Approve proposed changes to published content'),
        'applicable' => $is_moderated && !$only_authors_can_create,
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $is_moderated && !$only_authors_can_create ? $this->t('Update own published content without approval') : $this->t('Update own published content'),
        'permitted' => [
          'member' => !$is_moderated && !$only_authors_can_create,
          'author' => TRUE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Update any content'),
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Request changes on content from other users'),
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Request deletion of own content, pending approval'),
        'applicable' => $is_moderated && !$only_authors_can_create,
        'permitted' => [
          'member' => TRUE,
          'author' => FALSE,
          'facilitator' => FALSE,
          'owner' => FALSE,
        ],
      ],
      [
        'description' => $this->t('Approve requested deletion of content'),
        'applicable' => $is_moderated && !$only_authors_can_create,
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $is_moderated && !$only_authors_can_create ? $this->t('Delete own content without approval') : $this->t('Delete own content'),
        'permitted' => [
          'member' => !$is_moderated && !$only_authors_can_create,
          'author' => TRUE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Delete any content'),
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Manage users'),
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
      [
        'description' => $this->t('Archive content'),
        'permitted' => [
          'member' => FALSE,
          'author' => FALSE,
          'facilitator' => TRUE,
          'owner' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'joinup_group_membership_permissions_info';
  }

}
