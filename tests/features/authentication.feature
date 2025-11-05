@api
Feature: User authentication
  In order to protect the integrity of the website
  As a product owner
  I want to make sure users with various roles can only access pages they are authorized to

  Scenario Outline: Anonymous user can access public pages
    Given I am not logged in
    Then I visit "<path>"

    Examples:
      | path          |
      | collections   |
      | user/login    |
      | user/register |

  Scenario Outline: Anonymous user cannot access restricted pages
    Given I am not logged in
    When I go to "<path>"
    Then I should see the heading "Sign in to continue"

    Examples:
      | path                                                |
      | admin                                               |
      | admin/config                                        |
      | admin/config/search/redirect                        |
      | admin/content                                       |
      | admin/content/files                                 |
      | admin/content/media                                 |
      | admin/custom-landing-pages                          |
      | admin/custom-landing-pages/add                      |
      | admin/homepage-topics                               |
      | admin/legal-notice                                  |
      | admin/legal-notice/add                              |
      | admin/people                                        |
      | admin/structure                                     |
      | admin/structure/compatibility-document              |
      | admin/structure/compatibility-document/display      |
      | admin/structure/compatibility-document/form-display |
      | admin/structure/media                               |
      | admin/tmgmt/job_items                               |
      | admin/tmgmt/jobs                                    |
      | admin/tmgmt/sources                                 |
      | admin/tmgmt/cart                                    |
      | admin/tmgmt/translators                             |
      | admin/tmgmt/settings                                |
      | announcements                                       |
      | api/assessments/v1                                  |
      | collection/portal/revisions                         |
      | dashboard                                           |
      | dashboard/private-message-report                    |
      | licence                                             |
      | media/add                                           |
      | node/add                                            |
      | outdated-content-threshold                          |
      | propose/collection                                  |
      | user/subscriptions                                  |

  Scenario Outline: Authenticated user can access pages they are authorized to
    Given I am logged in as a user with the "authenticated" role
    Then I visit "<path>"

    Examples:
      | path               |
      | api/assessments/v1 |
      | collections        |
      | propose/collection |
      | user               |
      | user/subscriptions |
      | sparql             |

  Scenario Outline: Authenticated user cannot access site administration
    Given I am logged in as a user with the "authenticated" role
    When I go to "<path>"
    Then I should get an access denied error

    Examples:
      | path                                                |
      | admin                                               |
      | admin/config                                        |
      | admin/content                                       |
      | admin/content/files                                 |
      | admin/content/media                                 |
      | admin/content/messages                              |
      | admin/custom-landing-pages                          |
      | admin/custom-landing-pages/add                      |
      | admin/homepage-topics                               |
      | admin/legal-notice                                  |
      | admin/legal-notice/add                              |
      | admin/people                                        |
      | admin/structure                                     |
      | admin/structure/compatibility-document              |
      | admin/structure/compatibility-document/display      |
      | admin/structure/compatibility-document/form-display |
      | admin/structure/media                               |
      | admin/tmgmt/job_items                               |
      | admin/tmgmt/jobs                                    |
      | admin/tmgmt/sources                                 |
      | admin/tmgmt/cart                                    |
      | admin/tmgmt/translators                             |
      | admin/tmgmt/settings                                |
      | announcements                                       |
      | collection/portal/revisions                         |
      | dashboard                                           |
      | dashboard/private-message-report                    |
      | licence                                             |
      | media/add                                           |
      | node/add                                            |
      | outdated-content-threshold                          |

  Scenario Outline: Authenticated user cannot access restricted non-HTML URLs.
    Given I am logged in as a user with the "authenticated" role
    When I go to "<path>"
    Then the response status code should be 403

    Examples:
      | path                                        |
      | admin/structure/webform                     |
      | admin/structure/webform/submissions/manage  |

  Scenario Outline: Moderator can access pages they are authorized to
    Given I am logged in as a user with the "moderator" role
    Then I visit "<path>"

    Examples:
      | path                                                          |
      | admin/content/files                                           |
      | admin/content/media                                           |
      | admin/homepage-topics                                         |
      | admin/legal-notice                                            |
      | admin/legal-notice/add                                        |
      | admin/people                                                  |
      | admin/structure/compatibility-document                        |
      | admin/structure/webform                                       |
      | admin/structure/webform/manage/assessment/results/submissions |
      | admin/structure/webform/submissions/manage                    |
      | announcements                                                 |
      | dashboard                                                     |
      | licence                                                       |
      | media/add                                                     |
      | outdated-content-threshold                                    |
      | propose/collection                                            |

  Scenario Outline: Moderator cannot access restricted pages
    Given I am logged in as a user with the "moderator" role
    When I go to "<path>"
    Then I should get an access denied error

    Examples:
      | path                                                |
      | admin                                               |
      | admin/config                                        |
      | admin/config/search/redirect                        |
      | admin/content                                       |
      | admin/structure                                     |
      | admin/structure/compatibility-document/display      |
      | admin/structure/compatibility-document/form-display |
      | admin/structure/media                               |
      | admin/structure/webform/manage/assessment           |
      | admin/structure/webform/manage/assessment/settings  |
      | admin/tmgmt/cart                                    |
      | admin/tmgmt/job_items                               |
      | admin/tmgmt/jobs                                    |
      | admin/tmgmt/settings                                |
      | admin/tmgmt/sources                                 |
      | admin/tmgmt/translators                             |
      | node/add                                            |

  Scenario Outline: Administrator can access pages they are authorized to
    Given I am logged in as a user with the "administrator" role
    Then I visit "<path>"

    Examples:
      | path         |
      | collections  |
      | admin/people |

  Scenario Outline: Administrator cannot access pages intended for site building and development
    Given I am logged in as a user with the "administrator" role
    When I go to "<path>"
    Then I should get an access denied error

    Examples:
      | path                                       |
      | admin                                      |
      | admin/config                               |
      | admin/config/search/redirect               |
      | admin/content                              |
      | admin/legal-notice                         |
      | admin/legal-notice/add                     |
      | admin/structure                            |
      | node/add                                   |
