@api @group-f
Feature: Access tests for the developer role.

  Scenario Outline: Developers can access admin pages.
    Given I am logged in as a developer
    Then I go to "<page>"
    Then the response status code should be 200

    Examples:
      | page                                        |
#      | /dashboard                                  |
#      | /admin/custom-landing-pages                 |
      | /admin/content                              |
#      | /admin/content/comment                      |
#      | /admin/content/meta-entity                  |
#      | /admin/content/poll                         |
#      | /admin/content/rdf                          |
#      | /admin/content/rdf-meta-entity              |
#      | /admin/content/sitewide-alert               |
#      | /admin/content/files                        |
#      | /admin/content/messages                     |
#      | /admin/content/eif-wrapper                  |
#      | /admin/content/media                        |
#      | /admin/legal-notice                         |
#      | /admin/config/system/symfony-mailer-lite    |
#      | /admin/reporting/distribution-downloads     |
#      | /admin/reporting/distribution-downloads/csv |
#      | /admin/stucture/block                       |
#      | /admin/stucture/entityqueue                 |
#      | /admin/config/user-interface/antibot        |
#      | /admin/config/people                        |
#      | /admin/config/people/account                |
#      | /admin/config/people/cas                    |
#      | /admin/config/system/mailsystem             |
#      | admin/config/system/cron                    |
#      | /admin/config/system/delete-orphans         |
#      | /admin/config/search/path                   |
#      | /admin/config/search/search-api             |
#      | /admin/config/search/server/joinup          |
#      | /admin/config/search/index/published        |
#      | /admin/config/search/index/unpublished      |
