@api @group-i
Feature: Variety tests for the developer role.

  Scenario Outline: Developers can access admin pages.
    Given I am logged in as a developer
    Then I go to "<page>"
    Then the response status code should be 200

    Examples:
      | page                                               |
      | /dashboard                                         |
      | /admin/custom-landing-pages                        |
      | /admin/content                                     |
      | /admin/content/meta-entity                         |
      | /admin/content/poll                                |
      | /admin/content/rdf                                 |
      | /admin/content/meta-entity                         |
      | /admin/content/files                               |
      | /admin/content/messages                            |
      | /admin/content/media                               |
      | /admin/legal-notice                                |
      | /admin/config/system/symfony-mailer-lite/transport |
      | /admin/reporting/distribution-downloads            |
      | /admin/reporting/distribution-downloads/csv        |
      | /admin/config/user-interface/antibot               |
      | /admin/config/people                               |
      | /admin/config/people/accounts                      |
      | /admin/config/people/cas                           |
      | /admin/config/system/mailsystem                    |
      | /admin/config/system/cron                           |
      | /admin/config/search/path                          |
      | /admin/config/search/search-api                    |
      | /admin/config/search/search-api/server/joinup      |
      | /admin/config/search/search-api/index/published    |
      | /admin/config/search/search-api/index/unpublished  |

  # Warning: This test is not supposed to pass locally. The block is supposed to
  # be shown ONLY in production. In order to test this, you need to alter the
  # access check in the warning block to allow it to show locally.
  # @see \Drupal\joinup_user\Plugin\Block\WarningMessageBlock::blockAccess
  Scenario: Only a developer sees the warning message.
    Given I am an anonymous user
    And I am on the homepage
    Then I should not see the text "Warning: You are working on production. Please, be extra cautious!"

    # Developers see the warning both in the front theme and the admin theme.
    Given I am logged in as a developer
    And I am on the homepage
    Then I should see the warning message "You are working on production. Please, be extra cautious!"

    When I go to "/admin/content"
    Then I should see the warning message "You are working on production. Please, be extra cautious!"

    # An authenticated user cannot see the message.
    Given I am logged in as an authenticated
    And I am on the homepage
    Then I should not see the text "Warning: You are working on production. Please, be extra cautious!"

  # TODO: Re-enable this test in ISAICP-9052
  @wip
  Scenario: Configuration is locked and unlocked on developers login and logout.
    Given users:
      | Username   | Roles     |
      | Some dev 1 | Developer |

    When I am logged in as "Some dev 1"
    Then the configuration should be locked

    When I go to "/dashboard"
    And I press "Disable config readonly"
    Then I should see the success message "Disabled config readonly"
    And the configuration should be unlocked
    And I should not see the button "Disable config readonly"
    And I should see the button "Enable config readonly"
    And I should see the warning message "Config readonly is disabled!"

    When I press "Enable config readonly"
    Then I should see the success message "Enabled config readonly"
    And the configuration should be locked
    And I should see the button "Disable config readonly"
    And I should not see the button "Enable config readonly"
    And I should not see the warning message "Config readonly is disabled!"

    When I press "Disable config readonly"
    And I am on the homepage
    And I click "Sign out"
    Then the configuration should be locked
