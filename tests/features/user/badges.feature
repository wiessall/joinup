@api @group-i
Feature: User badges
  In order to recognize verified members of the Interoperable Europe Community
  As a visitor of the Interoperable Europe Portal
  I want to see a badge on the user profile that signals IEC membership

  Background:
    Given the following "collection" content:
      | uuid                                 | title                          |
      | ba527094-00bc-45de-98fb-c732c6c66fec | Interoperable Europe Community |

  Scenario: The IEC member badge tracks the Interoperable Europe Community membership state.
    Given the following users:
      | Username      | E-mail                    |
      | Celia Mae     | celia.mae@monsters.co     |
      | Randall Boggs | randall.boggs@monsters.co |
    And the following "collection" content:
      | title          | state     |
      | Scarers United | published |
    And the following collection user memberships:
      | collection                     | user          | state   |
      | Interoperable Europe Community | Celia Mae     | pending |
      | Scarers United                 | Randall Boggs | active  |

    # A pending membership in the Interoperable Europe community does not award the badge.
    When I go to the public profile of "Celia Mae"
    Then I should not see the text "Interoperable Europe Community member"

    # An active member of a different community is not awarded the badge.
    When I go to the public profile of "Randall Boggs"
    Then I should not see the text "Interoperable Europe Community member"

    # A moderator approves the pending membership.
    Given I am logged in as a moderator
    And I am on the members page of "Interoperable Europe Community"
    And I check the box "Update the member Celia Mae"
    And I select "Approve the pending membership(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Approve the pending membership(s) was applied to 1 item."

    # Once approved, the badge appears on the user profile.
    When I am an anonymous user
    And I go to the public profile of "Celia Mae"
    Then I should see the text "Interoperable Europe Community member" in the "Header" region

#    # User losses badge if his membership in IEC is blocked
    When I am logged in as a moderator
    And I am on the members page of "Interoperable Europe Community"
    And I check the box "Update the member Celia Mae"
    And I select "Block the selected membership(s) " from "Action"
    And I press "Apply to selected items"
    Then I should see the success message " Block the selected membership(s)  was applied to 1 item."
    When I go to the public profile of "Celia Mae"
    Then I should not see the text "Interoperable Europe Community member" in the "Header" region
