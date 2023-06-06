@api @group-f
Feature: Github feed paragraph tests.

  Scenario: Add a github feed paragraph to a custom page.
    Given the following collection:
      | title | Paragraphs collection |
      | state | validated             |
    When I am logged in as a moderator
    And I go to the edit form of the "Paragraphs collection" collection
    And I press "Add Github feed"
    And I fill in the following:
      | Title | Github feed                             |
      | Url   | https://github.com/sweetrdf/easyrdf.git |
    And I press "Save"
