@api @group-c
Feature: Custom page save new draft workflow transition.
  In order to create draft revisions from published custom pages
  As a facilitator
  I need to test the save new draft button functionality.

  Scenario: Test save new draft, save as draft, and publish buttons
    Given users:
      | Username     | E-mail                   |
      | Mickey Mouse | mickey.mouse@example.com |
    And the following collection user memberships:
      | collection | user         | roles       |
      | The Portal | Mickey Mouse | facilitator |
    And custom_page content:
      | title          | collection | state     |
      | Silent Echo v2 | The Portal | published |

    When I am logged in as "Mickey Mouse"
    And I go to the edit form of the "Silent Echo v2" "custom page"
    And I fill in "Title" with "Silent Echo – Reborn"
    And I press "Save new draft"
    Then I should see the heading "Silent Echo v2"

    When I go to the edit form of the "Silent Echo v2" "custom page"
    Then the "Title" field should contain "Silent Echo – Reborn"
    And I fill in "Title" with "Silent Echo Extended"
    And I press "Save as draft"
    Then I should see the heading "Silent Echo v2"

    When I go to the edit form of the "Silent Echo v2" "custom page"
    Then the "Title" field should contain "Silent Echo Extended"
    And I press "Publish"
    Then I should see the heading "Silent Echo Extended"
