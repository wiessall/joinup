@api @group-e
Feature:
  As a site moderator/administrator
  When I'm logged in
  I want to be able to access the Interoperable Europe Portal reporting section.

  Scenario Outline: Test the general access to Reporting section.
    Given I am logged in as a user with the <role> role
    When I am on "<url>"
    Then I should get a <code> HTTP response

    Examples:
      | url                                          | role          | code |
      | /admin/reporting                             | authenticated | 403  |
      | /admin/reporting                             | moderator     | 200  |
      | /admin/reporting/actions-log                 | authenticated | 403  |
      | /admin/reporting/actions-log                 | moderator     | 200  |
      | /admin/reporting/distribution-downloads      | authenticated | 403  |
      | /admin/reporting/distribution-downloads      | administrator | 403  |
      | /admin/reporting/distribution-downloads      | moderator     | 200  |
      | /admin/reporting/distribution-downloads      | developer     | 200  |
      | /admin/reporting/distribution-downloads/csv  | authenticated | 403  |
      | /admin/reporting/distribution-downloads/csv  | administrator | 403  |
      | /admin/reporting/distribution-downloads/csv  | moderator     | 200  |
      | /admin/reporting/distribution-downloads/csv  | develop       | 200  |
      | /admin/reporting/export-user-list            | authenticated | 403  |
      | /admin/reporting/export-user-list            | moderator     | 200  |
      | /admin/reporting/group-administrators        | authenticated | 403  |
      | /admin/reporting/group-administrators        | moderator     | 200  |
      | /admin/reporting/legal-notice-report         | authenticated | 403  |
      | /admin/reporting/legal-notice-report         | moderator     | 200  |
      | /admin/reporting/messages-overview           | authenticated | 403  |
      | /admin/reporting/messages-overview           | moderator     | 200  |
      | /admin/reporting/solutions-by-type           | authenticated | 403  |
      | /admin/reporting/solutions-by-type           | moderator     | 200  |
      | /admin/reporting/solutions-by-licences       | authenticated | 403  |
      | /admin/reporting/solutions-by-licences       | moderator     | 200  |
      | /admin/reporting/statistics-per-group        | authenticated | 403  |
      | /admin/reporting/statistics-per-group        | moderator     | 200  |
      | /admin/reporting/subscribers-report          | authenticated | 403  |
      | /admin/reporting/subscribers-report          | moderator     | 200  |
      | /admin/reporting/subscribers-report/download | authenticated | 403  |
      | /admin/reporting/subscribers-report/download | moderator     | 200  |

  Scenario Outline: Anonymous user cannot access restricted pages
    Given I am not logged in
    When I go to "<path>"
    Then I should see the heading "Sign in to continue"

    Examples:
      | path                                   |
      | admin/reporting/distribution-downloads |
      | admin/reporting/export-user-list       |
      | admin/reporting/group-administrators   |
      | admin/reporting/solutions-by-licences  |
      | admin/reporting/solutions-by-type      |
      | admin/reporting/subscribers-report     |

  Scenario Outline: Anonymous user cannot access restricted non-HTML URLs.
    Given I am not logged in
    When I go to "<path>"
    Then the response status code should be 403

    Examples:
      | path                                        |
      | admin/reporting/distribution-downloads/csv  |
      | admin/reporting/subscribers-report/download |

  Scenario: Links should be visible on the reporting page for a moderator.
    Given I am logged in as a user with the moderator role
    And I am on "/admin/reporting"
    Then I should see the following links:
      | Group administrators and facilitators |
      | Export user list                      |
      | Site-wide business log                |
      | Solutions by solution type            |
      | Solutions by licences                 |
      | Statistics per group                  |
      | Subscribers report                    |
      | Legal notice report                   |
      | Messages overview                     |
      | Distribution downloads                |
