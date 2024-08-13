@api @group-e
Feature:
  As a moderator of the website
  in order to better manage all content
  I need to be able to access operations directly through the contextual links.

  Scenario: Revisions link availability.
    Given collection:
      | title | Revisions collection |
      | state | published            |
    When I am logged in as a moderator
    And I go to the "Revisions collection" collection
    And I click "Add news" in the plus button menu
    And I fill in the following:
      | Short title | Revisions collection published          |
      | Headline    | Revisions collection has been published |
      | Abstract    | Revisions collection has been published |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | We are proud to announce another useless test entity. |
      | field_body:format | content_editor                                        |
    And I select "EU and European Policies" from "Topic"
    And I press "Save as draft"

    # Edit the news to create a new revision.
    And I click "Edit" in the "Entity actions" region
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | We are proud (well not that proud) to announce another useless test entity. |
      | field_body:format | content_editor                                                              |
    And I press "Save as draft"

    And I click "Revisions" in the "Entity actions" region
    And I click the last "Delete" link
    And I press "Delete"
    Then I should see the heading "Revisions collection has been published"
    And I should see the link "Revisions" in the "Entity actions" region

    When I am logged in as a facilitator of the "Revisions collection" collection
    And I go to the homepage of the "Revisions collection" collection
    Then I should not see the link "Revisions" in the "Entity actions" region

  Scenario: Only solutions should have the share contextual link available.
    Given collection:
      | title | Share collection |
      | state | published        |
    And contact_information content:
      | name    | email                |
      | Somebody | somebody@example.com |
    And owner content:
      | name       |
      | Some owner |
    And solution:
      | title               | Share solution   |
      | state               | published        |
      | collection          | Share collection |
      | contact information | Somebody         |
      | owner               | Some owner       |

    When I am logged in as a user with the "moderator" role
    And I go to the "Share solution" solution
    Then I should see the contextual link "Share"

    And I visit the collection overview
    Then I should not see the contextual link "Share"
