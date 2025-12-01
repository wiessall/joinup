@api @group-i
Feature: Publication status of keywords depends on the status of the parent.

  Scenario: Keywords publication management.
    Given I am logged in as a facilitator of the "Portal support" collection
    And I go to the "Portal support" collection
    And I click "Add solution"
    And I fill in the following:
      | Title       | ASDF solution    |
      | Abstract    | Some abstract    |
      | Description | Some description |
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "English" options from "Language availability"
    And I press "Add new owner"
    And I fill in "Name" with "John Doe"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"

    And I fill in the following:
      # Contact information.
      | Name           | John Doe             |
      | E-mail address | john.doe@example.com |
    And I press "Create contact information"
    And I select "Unpublished Keyword" options from "Search keywords"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    Then I should see the heading "ASDF solution"
    And the publication state of the "Unpublished Keyword" "taxonomy_term" should be "unpublished"

    When I am logged in as a moderator
    And I go to the edit form of the "ASDF solution" solution
    And I press "Confirm"
    And I press "Publish"
    Then I should see the heading "ASDF solution"
    And the publication state of the "Unpublished Keyword" "taxonomy_term" should be "published"

    # Save a draft entity to ensure that there are no duplicate keywords
    # created.
    Given I click "Add news" in the plus button menu
    And I fill in the following:
      | Short title | Some news |
      | Headline    | Some news |
      | Abstract    | Dummy text      |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | Blah           |
      | field_body:format | content_editor |
    And I select "EU and European Policies" option from "Topic"
    And I select "Unpublished Keyword" options from "Search keywords"
    And I press "Save as draft"
    Then I should see the heading "Some news"
    And there should be 1 "taxonomy_term" with the name "Unpublished Keyword"

    # Save a draft item with the keyword and verify it does not unpublish it.
    Given I click "Add news" in the plus button menu
    And I fill in the following:
      | Short title | Some news again |
      | Headline    | Some news again |
      | Abstract    | Dummy text      |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | Blah           |
      | field_body:format | content_editor |
    And I select "EU and European Policies" option from "Topic"
    And I select "Unpublished Keyword" options from "Search keywords"
    And I press "Save as draft"
    Then I should see the heading "Some news again"
    And the publication state of the "Unpublished Keyword" "taxonomy_term" should be "published"

  @javascript
  Scenario: Unpublished keywords are not available in the autocomplete.
    Given I am logged in as a facilitator of the "Portal support" collection
    And I go to the "Portal support" collection
    And I open the plus button menu
    And I click "Add news"
    # Keywords from the testing_terms.yml are used.
    And I select "gam" options from "Search keywords"
    And I wait for AJAX to finish
    Then I should see the text "games"
    When I type "some" in the "Search keywords" autocomplete field
    And I wait for AJAX to finish
    Then I should not see the text "some unpublished tag"

  Scenario: Access to unpublished keywords are available to privileged users.
    Given I go to the "some unpublished tag" keyword
    Then I should see the heading "Sign in to continue"

    When I am logged in as a facilitator of the "Portal support" collection
    And I go to the "some unpublished tag" keyword
    Then I should get an access denied error

    When I am logged in as a user with the authenticated role
    And I go to the "some unpublished tag" keyword
    Then I should get an access denied error

    When I am logged in as a moderator
    And I go to the "some unpublished tag" keyword
    Then I should get an access denied error
