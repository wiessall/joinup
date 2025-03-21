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
      | /admin/content/meta-entity                         |
      | /admin/content/files                               |
      | /admin/content/messages                            |
      | /admin/content/media                               |
      | /admin/legal-notice                                |
      | /admin/config/system/symfony-mailer-lite/transport |
      | /admin/config/user-interface/antibot               |
      | /admin/config/people                               |
      | /admin/config/people/accounts                      |
      | /admin/config/people/cas                           |
      | /admin/config/system/mailsystem                    |
      | /admin/config/system/cron                          |
      | /admin/config/search/path                          |
      | /admin/config/search/search-api                    |
      | /admin/config/search/search-api/server/joinup      |
      | /admin/config/search/search-api/index/published    |
      | /admin/config/search/search-api/index/unpublished  |
