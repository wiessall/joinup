@api @group-d
Feature:
  In order to better organize the content
  I need to be able to move content between groups

  Scenario: Use the content management UI
    Given collection content:
      | title        | state     |
      | Vintage Art  | published |
      | Decadent Art | published |
    And user:
      | Username | Vasundhara Commentator |
    And document content:
      | title             | document type | collection  | state     | changed          |
      | The Panama Papers | Document      | Vintage Art | published | 2019-11-19 20:00 |
      | The Area 51 File  | Document      | Vintage Art | draft     | 2018-11-19 21:00 |
    And discussion content:
      | title               | collection  | state     | created    | published at     | changed    |
      | The Ultimate Debate | Vintage Art | published | 2016-11-19 | 2016-11-19       | 2016-11-19 |
    And comments:
      | message             | author                 | parent              | created          | changed    |
      | Conducting a Debate | Vasundhara Commentator | The Ultimate Debate | 2017-06-12 19:00 | 1497294000 |
    And event content:
      | title                    | collection  | state     |
      | Version 2.0 Launch Party | Vintage Art | published |
    And news content:
      | title                              | collection  | state     |
      | Exports Leap Despite Currency Gain | Vintage Art | published |
    And custom_page content:
      | title                | collection  | state     | changed          |
      | HOWTOs               | Vintage Art | published | 2016-11-19 20:00 |
      | Looking for Support? | Vintage Art | published | 2015-11-19 20:00 |
    And the following custom page navigation menu structure for the "Vintage Art" collection:
      | title                | parent |
      | Looking for Support? | HOWTOs |

    Given I am an anonymous user
    And I go to the homepage of the "Vintage Art" collection
    Then I should not see the link "Manage content"

    Given I am logged in as an "authenticated user"
    And I go to the homepage of the "Vintage Art" collection
    Then I should not see the link "Manage content"

    # Check if the facilitator can manage only own group.
    Given I am logged in as a facilitator of the "Decadent Art" collection
    And I go to the homepage of the "Vintage Art" collection
    Then I should not see the link "Manage content"

    Given I am logged in as a facilitator of the "Vintage Art" collection
    And I go to the homepage of the "Vintage Art" collection
    Then I should see the link "Manage content"
    And I click "Manage content"
    And I should see the heading "Manage content"

    # Archive as facilitator.
    Given I select the "The Panama Papers" row
    And I select "Archive content" from "Action"
    And I press "Apply to selected items"
    And I wait for the batch process to finish
    Then I should see "Proceeded updates to 1 entities"
    And I should see "19/11/2019 - 20:00"

    # Last activity for discussion.
    And I should see "12/06/2017 - 19:00"

    Given I am logged in as a moderator

    # Visit one of the pages to warm up the render caches. We can then later
    # check that no stale cached content is being shown.
    When I go to the homepage of the "Vintage Art" collection
    And I click "HOWTOs" in the "Navigation menu" region
    Then I should see the link "Vintage Art"

    # Start the actual test scenario as a moderator.
    When I go to the homepage of the "Vintage Art" collection
    Then I should see the link "Manage content"
    Given I click "Manage content"
    Then I should see the heading "Manage content"

    # Download the list as CSV.
    When I click "Download CSV"
    Then the response should contain "Type;Title;\"Created on\";\"Last update/activity\";State;URL"
    And the response should contain "Document;\"The Panama Papers\";"
    And the response should contain "/document/panama-papers"
    And the response should contain "Document;\"The Area 51 File\";"
    And the response should contain "/document/area-51-file"
    And the response should contain "Discussion;\"The Ultimate Debate\";"
    And the response should contain "/discussion/ultimate-debate"
    And the response should contain "Event;\"Version 2.0 Launch Party\";"
    And the response should contain "/event/version-20-launch-party"
    And the response should contain "News;\"Exports Leap Despite Currency Gain\";"
    And the response should contain "/news/exports-leap-despite-currency-gain"
    And the response should contain "\"Custom page\";HOWTOs;"
    And the response should contain "/collection/vintage-art/howtos"
    And the response should contain "\"Custom page\";\"Looking for Support?\";"
    And the response should contain "/collection/vintage-art/looking-support"

    When I go to the manage content page of "Vintage Art"

    # Archive action.
    Given I select the "The Ultimate Debate" row
    And I select "Archive content" from "Action"
    And I press "Apply to selected items"
    And I wait for the batch process to finish
    Then I should see "Proceeded updates to 1 entities"
    And I should see "12/06/2017 - 19:00"
    And the string value of the "revision_log" field in "The Ultimate Debate" content should be "Updated state to 'archived' by VBO action, from revision ID '<placeholder>' with state 'published'."

    # Check archived state.
    Given I select "Archived" from "State"
    And I press "Apply"
    Then I should see the link "The Panama Papers"
    And I should see the link "The Ultimate Debate"

    # Unarchive action.
    Given I select the "The Panama Papers" row
    And I select the "The Ultimate Debate" row
    And I select "Unarchive content" from "Action"
    And I press "Apply to selected items"
    And I wait for the batch process to finish
    Then I should see "Proceeded updates to 2 entities"

    # Check published state.
    Given I select "published" from "State"
    And I press "Apply"
    Then I should see the link "The Panama Papers"
    And I should see the link "The Ultimate Debate"
    And I should see "19/11/2019 - 20:00"
    And I should see "12/06/2017 - 19:00"
    And the string value of the "revision_log" field in "The Ultimate Debate" content should be "Updated state to 'published' by VBO action, from revision ID '<placeholder>' with state 'archived'."

    # Check warning message.
    Given I press "Reset"
    And I select the "HOWTOs" row
    And I select "Archive content" from "Action"
    And I press "Apply to selected items"
    And I wait for the batch process to finish
    Then I should see "Proceeded updates to 1 entities"
    And I should see "19/11/2016 - 20:00"

    # Select rows.
    Given I select the "The Panama Papers" row
    And I select the "The Ultimate Debate" row
    And I select the "Version 2.0 Launch Party" row
    And I select the "Exports Leap Despite Currency Gain" row
    And I select the "HOWTOs" row

    # Select the action.
    Given I select "Move to other group" from "Action"
    And I press "Apply to selected items"

    And I fill in "Select the destination collection or solution" with "Decadent Art"

    # Run the batch process.
    When I press "Apply"
    And I wait for the batch process to finish

    Then I should see "Document The Panama Papers group was changed to Decadent Art."
    And the "Decadent Art" collection should have a community content titled "The Panama Papers"

    Then I should see "News Exports Leap Despite Currency Gain group was changed to Decadent Art."
    And the "Decadent Art" collection should have a community content titled "Exports Leap Despite Currency Gain"

    And I should see "Discussion The Ultimate Debate group was changed to Decadent Art."
    And the "Decadent Art" collection should have a community content titled "The Ultimate Debate"

    And I should see "Event Version 2.0 Launch Party group was changed to Decadent Art."
    And the "Decadent Art" collection should have a community content titled "Version 2.0 Launch Party"

    And I should see "Custom page HOWTOs group was changed to Decadent Art."
    And the "Decadent Art" collection should have a custom page titled "HOWTOs"

    # The child page was moved too, even only its parent has been selected.
    And I should see "Child Custom page Looking for Support? group was changed too."
    And the "Decadent Art" collection should have a custom page titled "Looking for Support?"

    And I should see "Action processing results: Move to other group (5)."

    # Visit the source collection.
    Given I go to the "Vintage Art" collection
    And I should not see the "The Panama Papers" tile
    And I should not see the "Exports Leap Despite Currency Gain" tile
    And I should not see the text "Discussions" in the "Navigation menu"
    And I should not see the "Version 2.0 Launch Party" tile
    And I should not see the link "HOWTOs" in the "Navigation menu" region
    And I should not see the link "Looking for Support?" in the "Navigation menu" region

    # Visit the destination collection.
    Given I go to the "Decadent Art" collection
    And I should see the "The Panama Papers" tile
    And I should see the "Exports Leap Despite Currency Gain" tile
    And I should see the text "Discussions" in the "Navigation menu"
    And I should see the "Version 2.0 Launch Party" tile
    Then I should see the link "HOWTOs" in the "Navigation menu" region
    When I click "HOWTOs" in the "Navigation menu" region
    Then I should see the link "HOWTOs" in the "Navigation menu" region
    And I should see the link "Decadent Art" in the "Header"
    And I should not see the link "Looking for Support?" in the "Navigation menu" region

    # Try moving a child page without moving the parent page. This should move
    # the child to the root of the menu.
    And I go to the manage content page of "Decadent Art"
    And I select the "Looking for Support?" row
    And I select "Move to other group" from "Action"
    And I press "Apply to selected items"
    And I fill in "Select the destination collection or solution" with "Vintage Art"
    And I press "Apply"
    And I wait for the batch process to finish
    Then I should see "Custom page Looking for Support? group was changed to Vintage Art."
    And I should see "Action processing results: Move to other group (1)."
    And the "Vintage Art" collection should have a custom page titled "Looking for Support?"

    Given I go to the "Vintage Art" collection
    Then I should see the link "Looking for Support?" in the "Navigation menu" region
    When I click "Looking for Support?" in the "Navigation menu" region
    Then I should not see the "Looking for Support?" tile

  Scenario: Move a custom page to a solution
    Given collection content:
      | title       | state     |
      | Vintage Art | published |
    Given solution content:
      | title                | state     |
      | Vintage Solution Art | published |
    And custom_page content:
      | title                       | collection  | state     | changed          |
      | Will be moved to a solution | Vintage Art | published | 2015-11-19 20:00 |

    Given I am logged in as a moderator
    And I go to the manage content page of "Vintage Art"

    And I select the "Will be moved to a solution" row
    And I select "Move to other group" from "Action"
    And I press "Apply to selected items"
    And I fill in "Select the destination collection or solution" with "Vintage Solution Art"
    When I press "Apply"
    And I wait for the batch process to finish
    Then I should see "Custom page Will be moved to a solution group was changed to Vintage Solution Art."
    Then the "Vintage Solution Art" solution should have a community content titled "Will be moved to a solution"

  @javascript
  Scenario Outline: Able to select all items on the content management UI
    Given collection content:
      | title       | state     |
      | Vintage Art | published |
    And event content:
      | title                   | collection  | state     |
      | The Panama Papers Party | Vintage Art | published |
      | The Area 51 Party       | Vintage Art | published |

    When I am <role>
    And I go to the manage content page of "Vintage Art"
    Then the available options in the "Action" select should be "<options>"
    Then the "The Panama Papers Party" checkbox should not be checked
    And the "The Area 51 Party" checkbox should not be checked

    When I select the "Title" row
    Then the "The Panama Papers Party" checkbox should be checked
    And the "The Area 51 Party" checkbox should be checked

    Examples:
      | role                                                       | options                                                                       |
      | logged in as a facilitator of the "Vintage Art" collection | -- Select action -- , Archive content                                         |
      | logged in as a user with the moderator role                | -- Select action -- , Move to other group, Archive content, Unarchive content |

  Scenario: Releases can only be transferred to solutions.
    Given collection content:
      | title        | state     |
      | Collection 1 | published |
      | Collection 2 | published |
    Given solution content:
      | title      | state     | collection   |
      | Solution 1 | published | Collection 1 |
      | Solution 2 | published | Collection 2 |
    And event content:
      | title   | solution   | state     |
      | Event 1 | Solution 1 | published |
    And release content:
      | title     | release number | release notes | is version of | state     |
      | Release 1 | 1.0            | Release notes | Solution 1    | published |
    And distribution content:
      | title          | description | release   | access url         | state     |
      | Distribution 1 | Description | Release 1 | http://example.com | published |

    When I am logged in as a moderator
    And I go to the manage content page of "Solution 1"
    And I select the "Release 1 1.0" row
    # Distributions cannot be moved for now.
    # @see ISAICP-8960
    And I select the "Distribution 1" row
    # Select also an event to verify that the transfer is not blocked by the
    # release.
    And I select the "Event 1" row
    And I select "Move to other group" from "Action"
    And I press "Apply to selected items"
    And I fill in "Select the destination collection or solution" with "Collection 1"
    And I press "Apply"

    Then I should see "Release Release 1 was not moved as Collection 1 is a collection. Releases can only be moved to a different solution."
    Then I should see "Distribution Distribution 1 was not moved in Collection 1 as it is not a standalone distribution. Please, move the parent release instead."
    And I should see "Event Event 1 group was changed to Collection 1."

    When I select the "Release 1 1.0" row
    And I select "Move to other group" from "Action"
    And I press "Apply to selected items"
    And I fill in "Select the destination collection or solution" with "Solution 2"
    And I press "Apply"

    Then I should see "Release Release 1 group was changed to Solution 2."
    # The child distribution should have been moved too.
    And I should see "Distribution Distribution 1 group was changed to Solution 2."

    # Revisit the page to get rid of the messages.
    When I go to the manage content page of "Solution 1"
    Then I should not see the link "Release 1 1.0"
    And I should not see the link "Distribution 1"
    And I should not see the link "Event 1"

    When I go to the "Solution 2" solution
    Then I should see the "Release 1" tile
    And I should see the text "Release version 1.0"

    When I go to the manage content page of "Solution 2"
    Then I should see the link "Release 1 1.0"
    And I should see the link "Distribution 1"
