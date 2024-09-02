@api @group-f
Feature: Actions log
  In order to get a view on actions to our content
  As a facilitator
  I want to have access the actions log report

  Background:
    Given collections:
      | title         | state     |
      | Tilt and Turn | published |
    Given solution content:
      | title    | state     | collection    |
      | Skylight | published | Tilt and Turn |

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

  Scenario: Report lists actions.
    Given users:
      | Username         | E-mail                       |
      | Andre Munson     | andre.munson@example.com     |
      | Branson Winthrop | branson.winthrop@example.com |
      | Mary Shotgun     | mary.shotgun@example.com     |
      | Dave Crash       | dave.crash@example.com       |
    And the following joinup log:
      | user             | actor            | group         | type                      | message             | timestamp  |
      | Andre Munson     | Mary Shotgun     | Tilt and Turn | joinup:add_member         | Add x member        | 1721815281 |
      | Andre Munson     | Andre Munson     | Tilt and Turn | joinup:add_content        | Add x custom page   | 1721715282 |
      | Andre Munson     | Dave Crash       | Tilt and Turn | joinup:invite_member      | Invite x member     | 1721615283 |
      | Dave Crash       | Dave Crash       | Tilt and Turn | joinup:add_content        | Add x document      | 1721515284 |
      | Branson Winthrop | Branson Winthrop | Skylight      | joinup:add_content        | Add x event         | 1721415285 |
      | Branson Winthrop | Mary Shotgun     | Skylight      | joinup:block_membership   | Block x member      | 1721315286 |
      | Andre Munson     | Mary Shotgun     | Skylight      | joinup:deleted_membership | Delete x membership | 1721215287 |
      | Andre Munson     | Mary Shotgun     | Skylight      | joinup:add_role           | Add x author role   | 1721115288 |

    Given I am logged in as a moderator
    When I go to the actions log report for "Tilt and Turn"
    Then the "actions log report" table should be:
      | Date               | Type          | Message           | User         | Actor        |
      | 20/07/2024 - 22:41 | Add content   | Add x document    | Dave Crash   | Dave Crash   |
      | 22/07/2024 - 02:28 | Invite Member | Invite x member   | Andre Munson | Dave Crash   |
      | 23/07/2024 - 06:14 | Add content   | Add x custom page | Andre Munson | Andre Munson |
      | 24/07/2024 - 10:01 | Add member    | Add x member      | Andre Munson | Mary Shotgun |
    Then the content received from clicking the link "Download CSV" contains the following strings:
      | Date;Type;Message;User;Actor                                                         |
      | "20/07/2024 - 22:41";"Add content";"Add x document";"Dave Crash";"Dave Crash"        |
      | "22/07/2024 - 02:28";"Invite Member";"Invite x member";"Andre Munson";"Dave Crash"   |
      | "23/07/2024 - 06:14";"Add content";"Add x custom page";"Andre Munson";"Andre Munson" |
      | "24/07/2024 - 10:01";"Add member";"Add x member";"Andre Munson";"Mary Shotgun"       |

    When I go to the actions log report for "Skylight"
    Then the "actions log report" table should be:
      | Date               | Type               | Message             | User             | Actor            |
      | 16/07/2024 - 07:34 | Add role to member | Add x author role   | Andre Munson     | Mary Shotgun     |
      | 17/07/2024 - 11:21 | Deleted membership | Delete x membership | Andre Munson     | Mary Shotgun     |
      | 18/07/2024 - 15:08 | Block membership   | Block x member      | Branson Winthrop | Mary Shotgun     |
      | 19/07/2024 - 18:54 | Add content        | Add x event         | Branson Winthrop | Branson Winthrop |
    Then the content received from clicking the link "Download CSV" contains the following strings:
      | Date;Type;Message;User;Actor                                                                  |
      | "16/07/2024 - 07:34";"Add role to member";"Add x author role";"Andre Munson";"Mary Shotgun"   |
      | "17/07/2024 - 11:21";"Deleted membership";"Delete x membership";"Andre Munson";"Mary Shotgun" |
      | "18/07/2024 - 15:08";"Block membership";"Block x member";"Branson Winthrop";"Mary Shotgun"    |
      | "19/07/2024 - 18:54";"Add content";"Add x event";"Branson Winthrop";"Branson Winthrop"        |
