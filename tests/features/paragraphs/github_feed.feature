@api @group-f @http_mock
Feature: Github feed paragraph tests.
  The scenarios are split into 4 tests because re-saving the entity would
  invalidate the file cache and the tests would require connecting to Github.

  Scenario: Test the Github feed paragraph.
    Given the following collection:
      | title | Paragraphs collection |
      | state | validated             |
    And custom_page content:
      | title                   | collection            | state     |
      | Catalogue of paragraphs | Paragraphs collection | validated |
    When I am logged in as a moderator
    And I go to the "Catalogue of paragraphs" custom page
    And I click "Edit"
    And I press "Add Github feed"
    And I fill in the following:
      | Repository | https://github.com/openeuropa/oe_bootstrap_theme.git |

    When I fill in the following:
      | Type            | pull_request |
      | Number of items | 5            |
    And I press "Save"
    And I should see the heading "Pull requests (5)"
    And I should not see the heading "Comments"

    When I click "Edit"
    And I fill in the following:
      | Type            | events |
      | Number of items | 5      |
    And I press "Save"
    And I should see the heading "Activity feed (5)"

    When I click "Edit"
    And I fill in the following:
      | Type            | issue |
      | Number of items | 5     |
    And I press "Save"
    Then I should see the heading "Catalogue of paragraphs"
    And I should see the heading "Issues (5)"
    And I should not see the heading "Comments (5)"

    When I click "Edit"
    And I fill in the following:
      | Type            | issue |
      | Number of items | 5     |
    And I check "Include comments"
    And I press "Save"
    And I should see the heading "Issues (5)"
    And I should see the heading "Comments (1)"

    # Test the error message when the repository is invalid.
    When I click "Edit"
    And I fill in the following:
      | Repository | https://some_invalid_repository.com/nothing |
    And I press "Save"
    Then I should see the text "The repository must be a valid GitHub repository."

    # Test a repository that is not found and ensure that the page does not break.
    When I click "Edit"
    And I fill in the following:
      | Repository | https://github.com/some/repository |
    And I press "Save"
    Then I should see the heading "Catalogue of paragraphs"
