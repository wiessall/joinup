@api @group-h
Feature: Discussions added to collections
  In order to manage discussions
  As a collection member
  I need to be able to add "Discussion" content through UI.

  Scenario: "Add discussion" button should not be shown to normal members, authenticated users and anonymous users.
    Given the following collections:
      | title              | logo     | state     |
      | The Fallen History | logo.png | published |
      | White Sons         | logo.png | published |

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "The Fallen History" collection
    Then I should not see the link "Add discussion"

    When I am an anonymous user
    And I go to the homepage of the "The Fallen History" collection
    Then I should not see the link "Add discussion"

    When I am logged in as a member of the "The Fallen History" collection
    And I go to the homepage of the "The Fallen History" collection
    Then I should see the link "Add discussion"

    When I am logged in as a "facilitator" of the "The Fallen History" collection
    And I go to the homepage of the "The Fallen History" collection
    Then I should see the link "Add discussion"
    # I should not be able to add a discussion to a different collection
    When I go to the homepage of the "White Sons" collection
    Then I should not see the link "Add discussion"

    When I am logged in as a "moderator"
    And I go to the homepage of the "The Fallen History" collection
    Then I should see the link "Add discussion"

  @uploadFiles:test.zip
  Scenario: Add discussion as a facilitator.
    Given user:
      | Username    | kesha1988                             |
      | First name  | Kesha                                 |
      | Family name | Pontecorvo                            |
      | E-mail      | kesha.pontecorvo@ec-europa.example.eu |
    And collections:
      | title                  | logo     | state     |
      | The World of the Waves | logo.png | published |
    And the following collection user membership:
      | collection             | user      | roles       |
      | The World of the Waves | kesha1988 | facilitator |
    And I am logged in as kesha1988

    When I go to the homepage of the "The World of the Waves" collection
    Then I should not see the text "Discussions" in the "Navigation menu"

    When I click "Add discussion" in the plus button menu
    And I should see the heading "Add discussion"
    And the following fields should be present "Title, Content, Topic, Logo, Add a new file"
    # The entity is new, so the current workflow state should not be shown.
    And the following fields should not be present "Current workflow state, Motivation"
    And the following fields should not be present "Shared on"

    # Check required fields.
    And I attach the file "test.zip" to "Add a new file"
    And I press "Upload"
    And I press "Publish"
    Then I should see the following lines of text:
      | Title field is required.                       |
      | The Attachments field description is required. |
      | Content field is required.                     |

    When I fill in the following:
      | Title            | An amazing discussion                      |
      | Content          | This is going to be an amazing discussion. |
      | File description | The content of this file is mind blowing   |
    And I press "Publish"
    Then I should see the error message "Topic field is required."

    And I select "EU and European Policies" from "Topic"
    And I press "Publish"

    Then I should see the heading "An amazing discussion"

    # Verify that the author is visible on the page.
    And I should see the text "Kesha Pontecorvo"
    And I should see the success message "Discussion An amazing discussion has been created."
    And I should see the text "Discussions" in the "Navigation menu"
    And the "The World of the Waves" collection has a discussion titled "An amazing discussion"

    # Attachments should be visible.
    And I should see the text "Attachments"
    And I should see the link "The content of this file is mind blowing"
    And I should see the text "176 bytes"

    # Regression test: the workflow state should not be shown to the user.
    But I should not see the text "State" in the "Content" region
    And the response should not contain "Published<"

    # Check that the tile for the discussion is visible on the collection page.
    When I go to the discussions page of "The World of the Waves"
    Then I should see the text "Discussions" in the "Navigation menu"
    And I should see the link "An amazing discussion"
    And I should not see the text "Kesha Pontecorvo" in the "An amazing discussion" tile
    # Initially there are 0 comments on the discussion.
    And I should see the statistics "0" in the "An amazing discussion" tile

    # Create two new comments and check that the counter is incremented.
    Given comments:
      | message                | author    | parent                |
      | Product up and running | kesha1988 | An amazing discussion |
      | Smart contract         | kesha1988 | An amazing discussion |
    When I reload the page
    Then I should see the statistics "2" in the "An amazing discussion" tile

    # Check that an anonymous user can see the information.
    Given I am an anonymous user
    When I go to the "An amazing discussion" discussion
    Then I should see the text "The World of the Waves"
    And I should see the heading "An amazing discussion"
    And I should see the following lines of text:
      | This is going to be an amazing discussion. |
      | Attachments                                |
      | 176 bytes                                  |
    And I should see the link "The content of this file is mind blowing"

  @javascript @generateMedia @generateMediaTypes:discussion_logo @uploadFiles:logo.png
  Scenario: Test the image library widget.
    Given the following collection:
      | title | Stream of Dreams |
      | state | published        |
    And discussion content:
      | title       | collection       | state     | created   | content         |
      | Yellow Zeus | Stream of Dreams | published | yesterday | Sample content. |

    Given I am logged in as a moderator

    # Upload works.
    When I go to the edit form of the "Yellow Zeus" discussion
    And I attach the file "logo.png" to "Logo"
    And I wait for AJAX to finish

    # Picking-up pre-uploaded images works.
    When I remove the file from "Logo"
    And I wait for AJAX to finish

    And I select image #4 as discussion logo
    And I wait for AJAX to finish
    And I press "Update"
    And the "Yellow Zeus" discussion logo is image #4

  @javascript
  Scenario: Ensures deleting the last discussion, using contextual links, leads back to the collection HP (and not the discussion page as 403).
    Given the following collection:
      | title | Snack ideas |
      | state | published   |
    And discussion content:
      | title                | collection  | state     | content         |
      | Quick and easy snack | Snack ideas | published | Sample content. |

    Given I am logged in as a moderator
    When I go to the discussions page of "Snack ideas"
    And I click the contextual link "Delete" in the "Quick and easy snack" tile
    Then I should see the heading "Are you sure you want to delete the content item Quick and easy snack?"
    And I press "Delete" in the "Content" region
    Then I should see the success message "The Discussion Quick and easy snack has been deleted."
    And I should be on the homepage of the "Snack ideas" collection
