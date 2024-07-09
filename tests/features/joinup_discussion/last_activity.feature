@api @group-h
Feature: Last Activity feature
  As a user of the website
  I want to be able to see last activity inside a tile.

  Scenario: The discussion tile should show the last activity box.
    Given the following collections:
      | title                         | description       | state     |
      | Development through bricolage | Bricolage 101.    | published |
      | Carrots love tomatoes         | Truth is out now. | published |
    And discussion content:
      | title         | state     | collection                    | shared on             | created          | changed    |
      | Tools cabinet | published | Development through bricolage | Carrots love tomatoes | 01-01-2023       | 01-01-2023 |
    And users:
      | Username      | Roles     | E-mail                   | First name | Family name |
      | alicia__1997  |           | AliciaPotter@example.com | Alicia     | Potter      |
    When I go to the "Development through bricolage" collection
    Then I should see the text "Discussions" in the "Navigation menu"
    When I go to the discussions page of "Development through bricolage"
    And I should see the "Tools cabinet" tile
    And I should see the text "Last activity:" in the "Tools cabinet" tile
    And I should see the text "01/01/2023" in the "Tools cabinet" tile
    And the changed date of the "Tools cabinet" discussion is "01-02-2023 00:00"

    When I go to the discussions page of "Development through bricolage"
    Then I should see the text "Last activity:" in the "Tools cabinet" tile
    And I should see the text "01/02/2023" in the "Tools cabinet" tile

    Given I am an anonymous user
    When I go to the discussions page of "Development through bricolage"
    Then I should see the text "Last activity:" in the "Tools cabinet" tile
    And I should see the text "01/02/2023" in the "Tools cabinet" tile

    Given I am logged in as a moderator
    When I go to the "Tools cabinet" discussion
    And I enter "Mr scandal was doing something weird the other day." in the "Create comment" wysiwyg editor
    And I press "Post comment"
    And I go to the discussions page of "Development through bricolage"
    Then I should see the text "Last activity:" in the "Tools cabinet" tile
    And I should not see the text "01/02/2023" in the "Tools cabinet" tile
