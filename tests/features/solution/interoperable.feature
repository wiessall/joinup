@api @group-a
Feature: Interoperable solution flag

  @javascript
  Scenario: Flag/un-flag solution as interoperable.
    Given the following "collection" content:
      | title        |
      | Derby County |
    Given the following "solution" content:
      | title            | collection   |
      | Crest identity   | Derby County |
      | Colours identity | Derby County |

    Given I am logged in as a user with the "interoperability_validator" role
    When I go to the homepage of the "Crest identity" solution
    And I click "Set interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed
    And I click "Remove interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed
    When I go to the homepage of the "Colours identity" solution
    And I click "Set interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed

    Given I am not logged in
    And I go to the homepage of the "Derby County" collection
    Then I should not see the text "Interoperable" in the "Crest identity" tile
    And I should see the text "Interoperable" in the "Colours identity" tile
    When I go to the homepage of the "Crest identity" solution
    Then I should not see the text "Interoperable" in the "Header" region
    When I go to the homepage of the "Colours identity" solution
    Then I should see the text "Interoperable" in the "Header" region

    Given I am logged in as a "facilitator" of the "Derby County" collection
    When I go to the homepage of the "Crest identity" solution
    Then I should not see the text "Interoperable" in the "Header" region
    When I go to the homepage of the "Colours identity" solution
    Then I should see the text "Interoperable" in the "Header" region

    Given I am logged in as a moderator
    When I go to the homepage of the "Crest identity" solution
    And I click "Set interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed
    And I click "Remove interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed
    When I go to the homepage of the "Colours identity" solution
    And I click "Remove interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed
    And I click "Set interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed
