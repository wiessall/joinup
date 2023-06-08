@api @group-f
Feature: Github feed paragraph tests.

  Scenario: Add a github feed paragraph to a custom page.
    Given the following collection:
      | title | Paragraphs collection |
      | state | validated             |
    And custom_page content:
      | title                   | collection            | state     |
      | Catalogue of paragraphs | Paragraphs collection | validated |
    When I am logged in as a moderator
    And I go to the "Catalogue of paragraphs" custom page
    And I click "Edit"
    And I should see the heading "Edit Custom page Catalogue of paragraphs"
    And I press "Add Github feed"
    And I fill in the following:
      | Heading         | Github feed                             |
      | URL             | https://github.com/sweetrdf/easyrdf.git |
      | Link text       | Read more                               |
      | Repository      | https://github.com/sweetrdf/easyrdf.git |
      | Type            | issue                                   |
      | Number of items | 2                                       |
    And I press "Save"

    Then I should see the heading "Catalogue of paragraphs"
    And I should see the heading "Github feed"
    And I should see the link "Read more"
    And I should see the heading "Issues (2)"
