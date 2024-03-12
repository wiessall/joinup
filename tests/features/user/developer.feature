@api @group-f
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
      | /admin/content/rdf-meta-entity                     |
      | /admin/content/files                               |
      | /admin/content/messages                            |
      | /admin/content/eif-wrapper                         |
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

  Scenario: Only a developer sees the warning message.
    Given I am an anonymous user
    And I am on the homepage
    Then I should not see the text "Warning: You are working on production. Please, be extra cautious!"

    # Developers see the warning both in the front theme and the admin theme.
    Given I am logged in as a developer
    And I am on the homepage
    Then I should see the warning message "Warning: You are working on production. Please, be extra cautious!"
    When I go to "/admin/content"
    Then I should see the warning message "Warning: You are working on production. Please, be extra cautious!"

    # An authenticated user cannot see the message.
    Given I am logged in as an authenticated
    And I am on the homepage
    Then I should not see the text "Warning: You are working on production. Please, be extra cautious!"
