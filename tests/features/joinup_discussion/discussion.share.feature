@api @group-e
Feature: Share discussion
  As a user of the website
  I want to be able to view the shared discussion information
  In order to have an overview of its content

  Background:
    Given the following "collection" content:
      | title                         | description       | state     |
      | Development through bricolage | Bricolage 101.    | published |
      | Carrots love tomatoes         | Truth is out now. | published |
    And the following "discussion" content:
      | title        | state     | collection                    | shared on             |
      | Tool cabinet | published | Development through bricolage | Carrots love tomatoes |

  Scenario: The discussion homepage should show the collections where it is shared on.
    When I go to the "Tool cabinet" discussion
    Then I should see the text "Shared on"
    And I should see the "Carrots love tomatoes" tile

  Scenario: The shared discussion should be displayed in listing.
    When I go to the discussions page of "Carrots love tomatoes"
    Then I should see the "Tool cabinet" tile

  Scenario: Single shared archived discussion should be displayed in listing.
    And the following "discussion" content:
      | title       | state    | collection            | shared on                     |
      | Chest combo | archived | Carrots love tomatoes | Development through bricolage |

    When I go to the discussions page of "Development through bricolage"
    When I click "Show archived"
    Then I should see the "Chest combo" tile
