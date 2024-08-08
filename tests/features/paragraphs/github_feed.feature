@api @group-f
Feature: Github feed paragraph tests.
  The scenarios are split into 4 tests because re-saving the entity would
  invalidate the file cache and the tests would require connecting to Github.

  @javascript
  Scenario Outline: Test the Github feed paragraph.
    Given the following <group>:
      | title | Paragraphs <group> |
      | state | published          |
    And custom_page content:
      | title                   | <group>            | state     | topic                   |
      | Catalogue of paragraphs | Paragraphs <group> | published | Statistics and Analysis |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Catalogue of paragraphs" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"
    Then I click "Choose component"
    And a modal should open
    And I click "Github feed"
    And I fill in "Repository" with "https://github.com/openeuropa/oe_bootstrap_theme.git" in the "Modal content" region
    And I fill in "Type" with "pull_request" in the "Modal content" region
    And I fill in "Number of items" with "5" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    And I should see the heading "Pull requests (5)"
    And I should see the link "Pull requests"
    And I should not see the heading "Comments"

    When I go to the edit form of the "Catalogue of paragraphs" "custom page"
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Type" with "events" in the "Modal content" region
    And I fill in "Number of items" with "5" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    And I should see the heading "Activity feed (5)"
    And I should see the link "Activity feed"

    When I go to the edit form of the "Catalogue of paragraphs" "custom page"
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Type" with "issue" in the "Modal content" region
    And I fill in "Number of items" with "5" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "Catalogue of paragraphs"
    And I should see the heading "Issues (5)"
    And I should see the link "Issues"
    And I should not see the heading "Comments (5)"

    When I go to the edit form of the "Catalogue of paragraphs" "custom page"
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Number of items" with "5" in the "Modal content" region
    And I fill in "Number of comments" with "5" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    And I should see the heading "Issues (5)"
    # Only one comment is available.
    And I should see the heading "Comments (1)"

    # Test the error message when the repository is invalid.
    When I go to the edit form of the "Catalogue of paragraphs" "custom page"
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Repository" with "https://some_invalid_repository.com/nothing" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    Then I should see the text "The repository must be a valid GitHub repository."

    # Test a repository that is not found and ensure that the page does not break.
    And I fill in "Repository" with "https://github.com/some/repository" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
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
      | state | published          |
    And custom_page content:
      | title                   | <group>            | state     | topic                   |
      | Catalogue of paragraphs | Paragraphs <group> | published | Statistics and Analysis |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Catalogue of paragraphs" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a facilitator of the "Paragraphs <group>" <group>
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"
    And I click "Choose component"
    And a modal should open
    And I click "Github feed"
    And I fill in the following:
      | Repository | https://github.com/openeuropa/oe_bootstrap_theme.git |

    When I fill in the following:
      | Type            | pull_request |
      | Number of items | 5            |
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    And I should see the heading "Pull requests (5)"
    And I go to the edit form of the "Catalogue of paragraphs" "custom page"

    Examples:
      | group      |
      | collection |
      | solution   |
