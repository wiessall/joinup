@api @group-f
Feature: Actions log
  In order to get a view on actions to our content
  As a facilitator
  I want to have access the actions log report

  Background:
    Given the following "collection" content:
      | title         |
      | Tilt and Turn |
    Given the following "solution" content:
      | title    | collection    |
      | Skylight | Tilt and Turn |
    Given users:
      | Username         | E-mail                       | First name | Family name |
      | Andre Munson     | andre.munson@example.com     | Andre      | Munson      |
      | Branson Winthrop | branson.winthrop@example.com | Branson    | Winthrop    |
      | Mary Miller      | mary.Miller@example.com      | Mary       | Miller      |
      | Dave Crash       | dave.crash@example.com       | Dave       | Crash       |
    And the following joinup log:
      | user             | actor            | group         | type                        | message             | timestamp  |
      | Andre Munson     | Mary Miller      | Tilt and Turn | joinup:add_member           | Add x member        | 1721815281 |
      | Andre Munson     | Andre Munson     | Tilt and Turn | joinup:add_content          | Add x custom page   | 1721715282 |
      | Andre Munson     | Dave Crash       | Tilt and Turn | joinup:invite_member        | Invite x member     | 1721615283 |
      | Dave Crash       | Dave Crash       | Tilt and Turn | joinup:add_content          | Add x document      | 1721515284 |
      | Branson Winthrop | Branson Winthrop | Skylight      | joinup:add_content          | Add x event         | 1721415285 |
      | Branson Winthrop | Mary Miller      | Skylight      | joinup:block_membership     | Block x member      | 1721315286 |
      | Andre Munson     | Mary Miller      | Skylight      | joinup:deleted_membership   | Delete x membership | 1721215287 |
      | Andre Munson     | Mary Miller      | Skylight      | joinup:add_role             | Add x author role   | 1721115288 |
      | Dave Crash       |                  | Skylight      | joinup:flag_interoperable   | Flagged             | 1721111290 |
      | Dave Crash       |                  | Skylight      | joinup:unflag_interoperable | Unflagged           | 1721110295 |
      | Dave Crash       |                  | Skylight      | joinup:delete_file          | Delete x file       | 1721110295 |

  Scenario: Access to report is limited.
    Given I am not logged in
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the text "Sign in to continue"
    When I go to the actions log report for "Skylight"
    Then I should see the text "Sign in to continue"

    Given I am logged in as a moderator
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the heading "Actions log"
    When I go to the actions log report for "Skylight"
    Then I should see the heading "Actions log"

    Given I am logged in as a facilitator of the "Tilt and Turn" collection
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the heading "Actions log"

    Given I am logged in as a facilitator of the "Skylight" solution
    When I go to the actions log report for "Skylight"
    Then I should see the heading "Actions log"

    Given I am logged in as a member of the "Tilt and Turn" collection
    When I go to the actions log report for "Tilt and Turn"
    Then I should see the text "Access denied"

    Given I am logged in as a member of the "Skylight" solution
    When I go to the actions log report for "Skylight"
    Then I should see the text "Access denied"

  Scenario Outline: System wide role actions.
    Given users:
      | Username       | First name | Family name | E-mail                     | Roles     |
      | Charles <role> | Charles    | <role>      | charles.darwin@example.com | Developer |

    Given I am logged in as "Charles <role>"
    When I go to the public profile of "Andre Munson"
    And I click "Edit" in the "Entity actions" region
    And I check the box "Licence manager"
    And I press "Save"
    And I click "Edit" in the "Entity actions" region
    And I uncheck the box "Licence manager"
    And I press "Save"

    When I visit "/admin/reporting/actions-log"
    Then I should see the following lines of text:
      | Successfully added the Licence manager role to Andre Munson by Charles <role>        |
      | Successfully removed the Licence manager role for the Andre Munson by Charles <role> |

    Examples:
      | role      |
      | developer |
      | moderator |

  Scenario: Report lists actions.
    Given I am logged in as a moderator
    When I go to the actions log report for "Tilt and Turn"
    Then the "actions log report" table should be:
      | Date               | Type          | Message           | User         | Actor        |
      | 20/07/2024 - 22:41 | Add content   | Add x document    | Dave Crash   | Dave Crash   |
      | 22/07/2024 - 02:28 | Invite Member | Invite x member   | Andre Munson | Dave Crash   |
      | 23/07/2024 - 06:14 | Add content   | Add x custom page | Andre Munson | Andre Munson |
      | 24/07/2024 - 10:01 | Add member    | Add x member      | Andre Munson | Mary Miller  |
    Then the content received from clicking the link "Download CSV" contains the following strings:
      | Date;Type;Message;User;Actor                                                         |
      | "20/07/2024 - 22:41";"Add content";"Add x document";"Dave Crash";"Dave Crash"        |
      | "22/07/2024 - 02:28";"Invite Member";"Invite x member";"Andre Munson";"Dave Crash"   |
      | "23/07/2024 - 06:14";"Add content";"Add x custom page";"Andre Munson";"Andre Munson" |
      | "24/07/2024 - 10:01";"Add member";"Add x member";"Andre Munson";"Mary Miller"        |

    When I go to the actions log report for "Skylight"
    Then the "actions log report" table should be:
      | Date               | Type                 | Message             | User             | Actor            |
      | 16/07/2024 - 06:11 | File delete          | Delete x file       | Dave Crash       |                  |
      | 16/07/2024 - 06:11 | Unflag interoperable | Unflagged           | Dave Crash       |                  |
      | 16/07/2024 - 06:28 | Flag interoperable   | Flagged             | Dave Crash       |                  |
      | 16/07/2024 - 07:34 | Add role             | Add x author role   | Andre Munson     | Mary Miller      |
      | 17/07/2024 - 11:21 | Deleted membership   | Delete x membership | Andre Munson     | Mary Miller      |
      | 18/07/2024 - 15:08 | Block membership     | Block x member      | Branson Winthrop | Mary Miller      |
      | 19/07/2024 - 18:54 | Add content          | Add x event         | Branson Winthrop | Branson Winthrop |
    Then the content received from clicking the link "Download CSV" contains the following strings:
      | Date;Type;Message;User;Actor                                                                 |
      | "16/07/2024 - 06:11";"File delete";"Delete x file";"Dave Crash";                             |
      | "16/07/2024 - 06:11";"Unflag interoperable";Unflagged;"Dave Crash";                          |
      | "16/07/2024 - 06:28";"Flag interoperable";Flagged;"Dave Crash";                              |
      | "16/07/2024 - 07:34";"Add role";"Add x author role";"Andre Munson";"Mary Miller"             |
      | "17/07/2024 - 11:21";"Deleted membership";"Delete x membership";"Andre Munson";"Mary Miller" |
      | "18/07/2024 - 15:08";"Block membership";"Block x member";"Branson Winthrop";"Mary Miller"    |
      | "19/07/2024 - 18:54";"Add content";"Add x event";"Branson Winthrop";"Branson Winthrop"       |

  Scenario: Filter node reports.
    Given I am logged in as a moderator
    When  I go to the actions log report for "Skylight"
    When I fill in "Date from" with "07/17/2024"
    And I fill in "Date to" with "07/20/2024"
    And I fill in "User" with "Branson"
    And I press "Apply"
    Then the "actions log report" table should be:
      | Date               | Type             | Message        | User             | Actor            |
      | 18/07/2024 - 15:08 | Block membership | Block x member | Branson Winthrop | Mary Miller      |
      | 19/07/2024 - 18:54 | Add content      | Add x event    | Branson Winthrop | Branson Winthrop |
    Then the content received from clicking the link "Download CSV" contains the following strings:
      | Date;Type;Message;User;Actor                                                              |
      | "18/07/2024 - 15:08";"Block membership";"Block x member";"Branson Winthrop";"Mary Miller" |
      | "19/07/2024 - 18:54";"Add content";"Add x event";"Branson Winthrop";"Branson Winthrop"    |

    When I go to the actions log report for "Skylight"
    When I fill in "Date from" with "07/17/2024"
    And I fill in "Date to" with "07/20/2024"
    And I fill in "Actor" with "Miller"
    And I press "Apply"
    Then the "actions log report" table should be:
      | Date               | Type               | Message             | User             | Actor       |
      | 17/07/2024 - 11:21 | Deleted membership | Delete x membership | Andre Munson     | Mary Miller |
      | 18/07/2024 - 15:08 | Block membership   | Block x member      | Branson Winthrop | Mary Miller |
    Then the content received from clicking the link "Download CSV" contains the following strings:
      | Date;Type;Message;User;Actor                                                                 |
      | "17/07/2024 - 11:21";"Deleted membership";"Delete x membership";"Andre Munson";"Mary Miller" |
      | "18/07/2024 - 15:08";"Block membership";"Block x member";"Branson Winthrop";"Mary Miller"    |

  Scenario: Filter site wide reports.
    Given I am logged in as a moderator
    When  I visit "/admin/reporting/actions-log"
    When I fill in "Date from" with "07/17/2024"
    And I fill in "Date to" with "07/20/2024"
    And I fill in "User" with "Branson"
    And I press "Apply"
    Then the "actions log report" table should be:
      | Date               | Type             | Message        | User             | Actor            | Group    |
      | 18/07/2024 - 15:08 | Block membership | Block x member | Branson Winthrop | Mary Miller      | Skylight |
      | 19/07/2024 - 18:54 | Add content      | Add x event    | Branson Winthrop | Branson Winthrop | Skylight |
    #Here i should test downloaded CSV, but assertLinkedContentContainsStrings is not working correctly for this scenario.

    When I visit "/admin/reporting/actions-log"
    When I fill in "Date from" with "07/17/2024"
    And I fill in "Date to" with "07/20/2024"
    And I fill in "Actor" with "Miller"
    And I press "Apply"
    Then the "actions log report" table should be:
      | Date               | Type               | Message             | User             | Actor       | Group    |
      | 17/07/2024 - 11:21 | Deleted membership | Delete x membership | Andre Munson     | Mary Miller | Skylight |
      | 18/07/2024 - 15:08 | Block membership   | Block x member      | Branson Winthrop | Mary Miller | Skylight |
    #Here i should test downloaded CSV, but assertLinkedContentContainsStrings is not working correctly for this scenario.
