@api @group-c
Feature: Custom page workflow transitions.
  In order to manage custom page states
  As a facilitator
  I need to test workflow buttons functionality.

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
    And I see h1 span element with title "Silent Echo v2"

    When I go to the edit form of the "Silent Echo v2" "custom page"
    Then the "Title" field should contain "Silent Echo – Reborn"
    And I fill in "Title" with "Silent Echo Extended"
    And I press "Save as draft"
    And I see h1 span element with title "Silent Echo v2"

    When I go to the edit form of the "Silent Echo v2" "custom page"
    Then the "Title" field should contain "Silent Echo Extended"
    And I press "Publish"
    And I see h1 span element with title "Silent Echo Extended"

  Scenario: Test unpublish transition makes page unavailable to end users
    Given custom_page content:
      | title       | collection | state     |
      | Help Center | The Portal | published |

    When I am an anonymous user
    And I go to the "Help Center" custom_page
    And I see h1 span element with title "Help Center"

    When I am logged in as a moderator
    And I go to the edit form of the "Help Center" "custom page"
    And I press "Unpublish"
    Then the workflow state of the "Help Center" content should be "unpublished"

    When I am logged in as an "authenticated user"
    And I go to the "Help Center" custom_page
    Then I should get an access denied error
