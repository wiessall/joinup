@api @group-i
Feature: User badges
  In order to recognize verified members of the Interoperable Europe Community
  As a visitor of the Interoperable Europe Portal
  I want to see a badge on the user profile that signals IEC membership

  Background:
    Given the following "collection" content:
      | uuid                                 | title                          |
      | ba527094-00bc-45de-98fb-c732c6c66fec | Interoperable Europe Community |

  Scenario: An active member of the Interoperable Europe community is awarded the IEC member badge.
    Given the following users:
      | Username      | E-mail                    |
      | Mike Wazowski | mike.wazowski@monsters.co |
    And the following collection user memberships:
      | collection                     | user          | state  |
      | Interoperable Europe Community | Mike Wazowski | active |

    When I go to the public profile of "Mike Wazowski"
    Then I should see the text "Interoperable Europe Community member" in the "Header" region

  Scenario: A pending membership in the Interoperable Europe community does not award the IEC member badge.
    Given the following users:
      | Username       | E-mail                       |
      | James Sullivan | james.sullivan@monsters.co   |
    And the following collection user memberships:
      | collection                     | user           | state   |
      | Interoperable Europe Community | James Sullivan | pending |

    When I go to the public profile of "James Sullivan"
    Then I should not see the text "Interoperable Europe Community member"

  Scenario: An active member of a different community is not awarded the IEC member badge.
    Given the following users:
      | Username      | E-mail                    |
      | Randall Boggs | randall.boggs@monsters.co |
    And the following "collection" content:
      | title          | state     |
      | Scarers United | published |
    And the following collection user memberships:
      | collection     | user          | state  |
      | Scarers United | Randall Boggs | active |

    When I go to the public profile of "Randall Boggs"
    Then I should not see the text "Interoperable Europe Community member"

  Scenario: The IEC member badge is awarded after a pending Interoperable Europe membership is approved.
    Given the following users:
      | Username    | E-mail                  |
      | Celia Mae   | celia.mae@monsters.co   |
    And the following collection user memberships:
      | collection                     | user      | state   |
      | Interoperable Europe Community | Celia Mae | pending |

    # The badge should not be present while the membership is still pending.
    When I go to the public profile of "Celia Mae"
    Then I should not see the text "Interoperable Europe Community member"

    # A moderator approves the pending membership.
    Given I am logged in as a moderator
    And I am on the members page of "Interoperable Europe Community"
    And I check the box "Update the member Celia Mae"
    And I select "Approve the pending membership(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Approve the pending membership(s) was applied to 1 item."

    # Once approved, the badge should appear on the user profile.
    When I am an anonymous user
    And I go to the public profile of "Celia Mae"
    Then I should see the text "Interoperable Europe Community member" in the "Header" region
