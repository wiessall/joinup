@api @group-i
Feature: Publication status of keywords depends on the status of the parent.

  Scenario: Keywords publication management.
    Given I am logged in as a facilitator of the "The Portal" collection
    And I go to the "The Portal" collection
    And I click "Add solution"
    And I fill in the following:
      | Title       | ASDF solution    |
      | Abstract    | Some abstract    |
      | Description | Some description |
    And I select "Complete Solution" from "Solution classification"
    And I select "Defense" from "Domain"
    And I select "HR" from "Topic"
    And I select "English" from "Language availability"
    And I press "Add new owner"
    And I fill in "Name" with "John Doe"
    And I check "Company"
    And I fill in "Country" with "European Union"
    And I press "Create owner"

    And I fill in the following:
      # Contact information.
      | Name           | John Doe             |
      | E-mail address | john.doe@example.com |
    And I press "Create contact information"
    And I fill in "Search keywords" with "Unpublished Keyword"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    Then I should see the heading "ASDF solution"
    And the publication state of the "Unpublished Keyword" "taxonomy_term" should be "unpublished"

    When I am logged in as a moderator
    And I go to the edit form of the "ASDF solution" solution
    And I press "Publish"
    Then I should see the heading "ASDF solution"
    And the publication state of the "Unpublished Keyword" "taxonomy_term" should be "published"

    # Save a draft item with the keyword and verify it does not unpublish it.
    Given I click "Add news" in the plus button menu
    And I fill in the following:
      | Short title | Some news again |
      | Headline    | Some news again |
      | Abstract    | Dummy text      |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | Blah           |
      | field_body:format | content_editor |
    And I select "EU and European Policies" from "Topic"
    And I fill in "Search keywords" with "Unpublished Keyword"
    And I press "Save as draft"
    Then I should see the heading "Some news again"
    And the publication state of the "Unpublished Keyword" "taxonomy_term" should be "published"
