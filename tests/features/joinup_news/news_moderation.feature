@api @group-f
# This features tests the workflow transitions.
#
# The steps @Then I go to the "news" content :title edit screen
# can be tested through the UI by going to the page of the content and press
# the edit button. But there are many edit buttons on the screen including
# the contextual links so there can be a false positive when searching for the button
# or when pressing an 'Edit' button.
#
# The steps @And the :title news content should (not )be published
# cannot be tested through the UI and are only for ensuring proper
# functionality.
Feature: News moderation.
  As a member, facilitator or collection owner
  In order to manage news about my collection
  I need to be able to have a workflow based news management system.

  Background:
    # The complete permission matrix is stored in configuration.
    # @see: modules/custom/joinup_news/config/install/joinup_news.settings.yml.
    Given users:
      | Username | E-mail                      | Roles     |
      | Batman   | adminOfWayneINC@example.com | moderator |
      | Superman | dailyPlanetEmployee@example.com |       |
      | Eagle    | WorldWarVeteran@example.com     |       |
      | Metallo  | kryptoniteEverywhere@example.com |      |
    Given collection content:
      | title          | group_moderation | state     | content_creation |
      | Justice League | no               | published | members          |
      | Legion of Doom | yes              | published | members          |
    And the following collection user memberships:
      | collection     | user     | roles       |
      | Justice League | Superman | owner       |
      | Justice League | Eagle    | member      |
      | Legion of Doom | Metallo  | facilitator |
    And "news" content:
      | title                   | headline                 | field_paragraphs_body | state     | author   | collection     | abstract | created    |
      | Creating Justice League | 6 Members to start with | TBD                   | draft     | Eagle    | Justice League | Abstract | now -2 day |
      | News in Proposed State  | Test news                | Test body             | proposed  | Eagle    | Justice League | Abstract | now -2 day |
      | Published News Item     | Published test news      | Published test body   | published | Eagle    | Justice League | Abstract | now -2 day |
      | Pre-moderated Draft     | Legion draft news        | Draft body            | draft     | Metallo  | Legion of Doom | Abstract | now -2 day |

  Scenario: Draft state doesn't change when facilitator edits news.
    Given I am logged in as "Eagle"
    When I go to the "Creating Justice League" news
    And I click "Edit"
    And I press "Save"
    Then the "Creating Justice League" news content should have the "draft" state

  Scenario: Members can see and add news to post-moderated collection.
    Given I am logged in as "Eagle"
    And I go to the homepage of the "Justice League" collection
    Then I should see the link "Add news"
    When I click "Add news"
    Then I should see the heading "Add news"
    And the following buttons should be present "Save as draft, Publish"
    And the following buttons should not be present "Propose, Request changes, Request deletion, Preview"

  Scenario: Create and publish news workflow.
    When I am logged in as "Eagle"
    And I go to the homepage of the "Justice League" collection
    And I click "Add news"
    When I fill in the following:
      | Short title | Eagle joins the JL |
      | Headline    | Eagle from WWII    |
      | Abstract    | Eagle abstract     |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | Specialized in close combat training |
      | field_body:format | content_editor                       |
    And I select "Employment and Support Allowance" options from "Topic"
    And I press "Save as draft"
    Then the "Eagle joins the JL" news content should not be published
    When I go to the "Eagle joins the JL" news
    And I click "Edit"
    And I press "Publish"
    Then the "Eagle joins the JL" news content should be published

  Scenario: Members can edit their own draft news.
    Given I am logged in as "Eagle"
    And I go to the "Creating Justice League" news
    Then I should see the link "Edit"
    When I click "Edit"
    Then I should not see the heading "Access denied"
    And the following buttons should be present "Save as draft, Publish"

  Scenario: Members can edit their own published news.
    Given I am logged in as "Eagle"
    And I go to the "Published News Item" news
    Then I should see the link "Edit"
    When I click "Edit"
    Then I should not see the heading "Access denied"
    And the following buttons should be present "Save new draft, Update"

  Scenario: Owner can manage news transitions.
    Given I am logged in as "Superman"
    And I go to the "News in Proposed State" news
    Then I should see the link "Edit"
    When I click "Edit"
    Then I should not see the heading "Access denied"
    And the following buttons should be present "Update, Publish"

  Scenario: Moderators can edit all news.
    Given I am logged in as "Batman"
    And I go to the "Creating Justice League" news
    Then I should see the link "Edit"
    When I click "Edit"
    Then I should not see the heading "Access denied"

  Scenario: News workflow state transitions work correctly.
    When I am logged in as "Superman"
    And I go to the "News in Proposed State" news
    Then the "News in Proposed State" "news" content should not be published
    When I click "Edit"
    And I press "Publish"
    Then the "News in Proposed State" "news" content should be published

  Scenario: Pre-moderated collection shows different buttons for facilitators.
    Given I am logged in as "Metallo"
    And I go to the homepage of the "Legion of Doom" collection
    And I click "Add news"
    Then I should see the heading "Add news"
    And the following buttons should be present "Save as draft, Publish, Propose"
    And the following buttons should not be present "Request changes, Request deletion, Preview"

  Scenario: Facilitators can edit their own draft content in pre-moderated collections.
    Given I am logged in as "Metallo"
    And I go to the "Pre-moderated Draft" news
    Then I should see the link "Edit"
    When I click "Edit"
    Then I should not see the heading "Access denied"
    And the following buttons should be present "Save as draft, Publish, Propose"

  Scenario: Cross-collection access control works correctly.
    Given I am logged in as "Metallo"
    And I go to the "Creating Justice League" news
    Then I should see the heading "Access denied"
