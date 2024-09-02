@api @group-a
Feature: Global Interoperable search
  As a user of the site I can find content marked as Interoperable.

  @javascript
  Scenario: User can find Interoperable items
    Given the following collections:
      | title          | state     |
      | Open standards | published |
    Given solution content:
      | title           | state     | collection     | description                                                                  |
      | eGovernment     | published | Open standards | It refers to the collaboration ability of cross-border services for citizens |
      | Military forces | published | Open standards | the ability of the forces of two or more nations to train                    |

    Given I am logged in as a user with the "interoperability_validator" role
    When I go to the homepage of the "eGovernment" solution
    And I click "Set interoperable flag" in the "Header" region

    When I am an anonymous user
    And I visit the search page
    Then the "Interoperable" checkbox facet should allow selecting the following values "Show only Interoperable Solutions"

    When I check the "Solutions (2)" checkbox from the "Content types" facet
    And I check the "Show only Interoperable Solutions" checkbox from the "Interoperable" facet
    Then the page should show the tiles "eGovernment"

    When I visit the search page
    And I check the "Collection (1)" checkbox from the "Content types" facet
    Then I should not see the text "Show only Interoperable Solutions"
