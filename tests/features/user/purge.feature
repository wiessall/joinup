@api @group-i
Feature: Automated approach for identifying and deleting inactive users

  Background:
    Given users:
      | Username       | Roles           | E-mail                     | Login       | Created     | Access      | Status  |
      | Cosmic Admin   | Administrator   | cosmic.admin@example.com   | -3 years    | -4 years    | -4 years    | active  |
      | Pixel Panda    | Moderator       | pixel.panda@example.com    | -2 years    | -2.5 years  | -2.5 years  | active  |
      | Licence Queen  | Licence manager | licence.queen@example.com  | -5 months   | -1 year     | -1 year     | active  |
      | Dev Phoenix    | Developer       | dev.phoenix@example.com    | -6 months   | -2 years    | -2 years    | active  |
      | Beta Bunny     |                 | beta.bunny@example.com     | -8 months   | -1 year     | -1 year     | blocked |
      | Orion Void     |                 | orion.void@example.com     | -1.5 years  | -2 years    | -2 years    | blocked |
      | Arion Truth    |                 | arion.truth@example.com    | -1.5 years  | -2 years    | -2 years    | active  |
      | Bunny Truth    |                 | bunny.truth@example.com    |             | -2 years    |             | active  |

  Scenario: Verify users to be notified.
    Given I set state "joinup_user_last_notify_run" to 0
    Then I mark all emails as read
    Then I run cron
    And the "purge_users" queue is empty
    And I run the "notification_users" queue worker
    Then 2 e-mails should have been sent
    And the email sent to "Bunny Truth" with subject "Important Notice - Action Required to Retain Your Interoperable Europe Portal Account" contains the following lines of text:
      | As part of our regular maintenance on the Interoperable Europe Portal and in order to ensure the highest level of security on the platform |
      | Should you wish to maintain your account, please click here in order to log in to your account before the described deletion date.         |
      | Should you have any questions or need assistance, please feel free to contact our support team by clicking on the Contact Support form.    |
    And the email sent to "Beta Bunny" with subject "Important Notice - Action Required to Retain Your Interoperable Europe Portal Account" contains the following lines of text:
      | As part of our regular maintenance on the Interoperable Europe Portal and in order to ensure the highest level of security on the platform |
      | Should you wish to maintain your account, please click here in order to log in to your account before the described deletion date.         |
      | Should you have any questions or need assistance, please feel free to contact our support team by clicking on the Contact Support form.    |
