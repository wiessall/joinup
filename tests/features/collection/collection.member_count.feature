@api @group-a
Feature: Collection homepage
  In order to have the users properly informed
  As an owner of the website
  I need to be able see the amount of users that are members of a collection

  Background:
    Given the following users:
      | Username    | Status  |
      | Thorin      | active  |
      | Fili        | active  |
      | Kili        | active  |
      | Bilbo       | blocked |
      | Some goblin | active  |
    And the following "collection" content:
      | title                                   | moderation | closed |
      | Under the mountain                      | yes        | no     |
      | Exactly the same collection, but closed | yes        | yes    |
    And the following collection user memberships:
      | collection                              | user   | roles       |
      | Under the mountain                      | Thorin | facilitator |
      | Under the mountain                      | Fili   |             |
      | Under the mountain                      | Kili   |             |
      | Under the mountain                      | Bilbo  |             |
      | Exactly the same collection, but closed | Thorin | facilitator |
      | Exactly the same collection, but closed | Fili   |             |
      | Exactly the same collection, but closed | Kili   |             |
      | Exactly the same collection, but closed | Bilbo  |             |

  Scenario: Counters are properly synced for open collections.
    When I go to the homepage of the "Under the mountain" collection
    Then I see the text "3 Members" in the "Header" region
    And the "Under the mountain" collection should have 4 active member
    And the "Under the mountain" collection should have 0 pending members
    And the "Under the mountain" collection should have 0 blocked members
    When I am logged in as "Some goblin"
    And I go to the homepage of the "Under the mountain" collection
    And I press "Join this collection"
    Then the "Under the mountain" collection should have 5 active member
    And I go to the homepage of the "Under the mountain" collection
    Then I see the text "4 Members" in the "Header" region
    And I am on the members page of "Under the mountain"
    Then the "Roles" field should contain the "- Any - (4)" option

    # Ensure that leaving a collection, updates the counters.
    When I click "Leave this collection"
    When I press "Confirm"
    Then I should see the success message "You are no longer a member of Under the mountain."
    And the "Under the mountain" collection should have 4 active member
    Then I see the text "3 Members" in the "Header" region
    And I am on the members page of "Under the mountain"
    Then the "Roles" field should contain the "- Any - (3)" option

  Scenario: Counters are properly synced for closed collections.
    When I go to the homepage of the "Exactly the same collection, but closed" collection
    Then I see the text "3 Members" in the "Header" region
    And the "Exactly the same collection, but closed" collection should have 4 active member
    And the "Exactly the same collection, but closed" collection should have 0 pending members
    And the "Exactly the same collection, but closed" collection should have 0 blocked members
    When I am logged in as "Some goblin"
    And I go to the homepage of the "Exactly the same collection, but closed" collection
    And I press "Join this collection"
    Then the "Exactly the same collection, but closed" collection should have 1 pending member
    And I go to the homepage of the "Exactly the same collection, but closed" collection
    Then I see the text "3 Members" in the "Header" region
    And I am on the members page of "Exactly the same collection, but closed"
    Then the "Roles" field should contain the "- Any - (3)" option

    # Approve the membership.
    Given I am logged in as "Thorin"
    And I am on the members page of "Exactly the same collection, but closed"
    Then I should not see the text "Bilbo"

    When I check the box "Update the member Some goblin"
    Then I select "Approve the pending membership(s)" from "Action"
    And I press "Apply to selected items"
    # Block Fili's membership
    And I check the box "Update the member Fili"
    Then I select "Block the selected membership(s)" from "Action"
    And I press "Apply to selected items"

    When I am an anonymous user
    And I go to the homepage of the "Exactly the same collection, but closed" collection
    Then I see the text "3 Members" in the "Header" region
    And the "Exactly the same collection, but closed" collection should have 4 active member
    And the "Exactly the same collection, but closed" collection should have 0 pending members
    And the "Exactly the same collection, but closed" collection should have 1 blocked member
    When I am on the members page of "Exactly the same collection, but closed"
    Then the "Roles" field should contain the "- Any - (3)" option
    Then I see the text "3 Members" in the "Header" region

  Scenario: Blocked users are not counted towards total member count.
    # Cache the collection overview page.
    When I am not logged in
    When I visit the collection overview page
    Then I should see the text "3" in the "Under the mountain" tile

    Given I am logged in as a moderator
    And I am on the homepage
    When I click "People"
    And I fill in "Name or email contains" with "Bilbo"
    And I press "Filter"
    Then I check "Bilbo"
    Then I select "Unblock the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Unblock the selected user(s) was applied to 1 item."
    When I am not logged in
    When I go to the homepage of the "Under the mountain" collection
    Then I should see the text "4 members" in the "Header" region
    And I am on the members page of "Under the mountain"
    Then the "Roles" field should contain the "- Any - (4)" option

    # Ensure that cache is invalidated properly for tiles in search api views.
    When I visit the collection overview page
    Then I should see the text "4" in the "Under the mountain" tile

    # Block the user to ensure again that the counters are updated.
    Given I am logged in as a moderator
    And I am on the homepage
    When I click "People"
    And I fill in "Name or email contains" with "Bilbo"
    And I press "Filter"
    Then I check "Bilbo"
    Then I select "Block the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Block the selected user(s) was applied to 1 item."
    When I am not logged in
    When I go to the homepage of the "Under the mountain" collection
    Then I should see the text "3 members" in the "Header" region
    And I am on the members page of "Under the mountain"
    Then the "Roles" field should contain the "- Any - (3)" option

    # Ensure that cache is invalidated properly for tiles in search api views.
    When I visit the collection overview page
    Then I should see the text "3" in the "Under the mountain" tile
