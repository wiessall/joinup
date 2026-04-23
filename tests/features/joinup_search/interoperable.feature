@api @group-a
Feature: Global Interoperable search
  As a user of the site I can find content marked as Interoperable.

  @javascript
  Scenario: User can find Interoperable items
    Given collection content:
      | title          |
      | Open standards |
    Given solution content:
      | title           | collection     | description                                                                  |
      | eGovernment     | Open standards | It refers to the collaboration ability of cross-border services for citizens |
      | Military forces | Open standards | the ability of the forces of two or more nations to train                    |

    Given I am logged in as a user with the "interoperability_validator" role
    When I go to the homepage of the "eGovernment" solution
    And I click "Set interoperable flag" in the "Header" region
    And a modal should open
    And I press "Confirm" in the "Modal buttons" region
    And the modal should be closed

    When I am an anonymous user
    And I visit the advanced search page
    Then the "Interoperable" checkbox facet should allow selecting the following values "Interoperable Europe Solutions"

    When I check the "Solutions (2)" checkbox from the "Content types" facet
    And I check the "Interoperable Europe Solutions" checkbox from the "Interoperable" facet
    Then the page should show the tiles "eGovernment"

    When I visit the advanced search page
    # 3 demo collections are installed by default.
    # @see joinup_test.install
    And I check the "Collections (3)" checkbox from the "Content types" facet
    Then I should not see the text "Interoperable Europe Solutions"
