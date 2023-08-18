@api @terms @group-f
Feature: Github feed paragraph tests.
  The scenarios are split into 4 tests because re-saving the entity would
  invalidate the file cache and the tests would require connecting to Github.

  Scenario Outline: Test the Github feed paragraph.
    Given the following <group>:
      | title | Paragraphs <group> |
      | state | validated          |
    And custom_page content:
      | title                   | <group>            | state     | topic |
      | Catalogue of paragraphs | Paragraphs <group> | published | Statistics and Analysis |
    When I am logged in as a moderator
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"
    And I click "Add Github feed" in the "Custom page body" paragraphs field
    And I fill in the following:
      | Repository | https://github.com/openeuropa/oe_bootstrap_theme.git |

    When I fill in the following:
      | Type            | pull_request |
      | Number of items | 5            |
    And I press "Update"
    And I should see the heading "Pull requests (5)"
    And I should not see the heading "Comments"

    When I click "Edit"
    And I fill in the following:
      | Type            | events |
      | Number of items | 5      |
    And I press "Update"
    And I should see the heading "Activity feed (5)"

    When I click "Edit"
    And I fill in the following:
      | Type            | issue |
      | Number of items | 5     |
    And I press "Update"
    Then I should see the heading "Catalogue of paragraphs"
    And I should see the heading "Issues (5)"
    And I should not see the heading "Comments (5)"

    And I go to the edit form of the "Catalogue of paragraphs" "custom page"
    And I fill in the following:
      | Type               | issue |
      | Number of items    | 5     |
      | Number of comments | 5     |
    And I press "Update"
    And I should see the heading "Issues (5)"
    # Only one comment is available.
    And I should see the heading "Comments (1)"

    # Test the error message when the repository is invalid.
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"
    And I fill in the following:
      | Repository | https://some_invalid_repository.com/nothing |
    And I press "Update"
    Then I should see the text "The repository must be a valid GitHub repository."

    # Test a repository that is not found and ensure that the page does not break.
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"
    And I fill in the following:
      | Repository | https://github.com/some/repository |
    And I press "Update"
    Then I should see the heading "Catalogue of paragraphs"

    Examples:
      | group      |
      | collection |
      | solution   |

  @javascript
  Scenario Outline: Facilitators can also access paragraphs.
    Given the following <group>:
      | title | Paragraphs <group> |
      | state | validated          |
    And custom_page content:
      | title                   | <group>            | state     | topic |
      | Catalogue of paragraphs | Paragraphs <group> | published | Statistics and Analysis |
    When I am logged in as a facilitator of the "Paragraphs <group>" <group>
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"
    And I click "Add Github feed" in the "Custom page body" paragraphs field
    And I fill in the following:
      | Repository | https://github.com/openeuropa/oe_bootstrap_theme.git |

    When I fill in the following:
      | Type            | pull_request |
      | Number of items | 5            |
    And I press "Update"
    And I should see the heading "Pull requests (5)"
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"

    # There should be a single paragraph with the Collapse button available.
    And I press "Collapse"
    # The only edit button in the main region is the one for the paragraph.
    And I should see the button "Edit" in the "Content" region
    And I should see the button "Remove" in the "Content" region

    Examples:
      | group      |
      | collection |
      | solution   |
