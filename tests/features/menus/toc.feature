@api @group-b
Feature: Custom pages in Table of Contents

  Background:
    Given the following collection content:
      | title          |
      | TOC Collection |

  Scenario: Links below the topmost page are rendered in the second level menu.
    And the following "custom_page" content:
      | title       | collection     | topic |
      | Page 1      | TOC Collection | HR    |
      | Page 2      | TOC Collection | HR    |
      | Subpage 1-1 | TOC Collection | HR    |
      | Subpage 1-2 | TOC Collection | HR    |
      | Subpage 2-1 | TOC Collection | HR    |
    And the following custom pages navigation menu structure for the "TOC Collection" collection:
      | title       | parent | weight |
      | Page 1      |        | 1      |
      | Page 2      |        | 2      |
      | Subpage 1-1 | Page 1 | 1      |
      | Subpage 1-2 | Page 1 | 2      |
      | Subpage 2-1 | Page 2 | 1      |
    When I am logged in as a member of the "TOC Collection" collection
    And I visit the "Page 1" "custom page"
    Then I should see the link "Subpage 1-1" in the "Second level navigation menu"
    And I should see the link "Subpage 1-2" in the "Second level navigation menu"
    But I should not see the link "Page 2" in the "Second level navigation menu"
    And I should not see the link "Subpage 2-1" in the "Second level navigation menu"

  Scenario: Show appropriate menu entries in the table of contents outline.
    And the following "custom_page" content:
      | title    | collection     | state     | topic |
      | TOCO 1   | TOC Collection | published | HR    |
      | TOCO 1-1 | TOC Collection | published | HR    |
      | TOCO 1-2 | TOC Collection | published | HR    |
    And the following custom pages navigation menu structure for the "TOC Collection" collection:
      | title    | parent | weight |
      | TOCO 1   |        | 1      |
      | TOCO 1-1 | TOCO 1 | 1      |
      | TOCO 1-2 | TOCO 1 | 2      |
    When I am logged in as a member of the "TOC Collection" collection
    And I visit the "TOCO 1" "custom page"
    Then I should not see the link "Up" in the "Table of contents outline"
    When I click "TOCO 1-1" in the "Table of contents outline"
    Then I should see the link "Up" in the "Table of contents outline"
    When I click "Up" in the "Table of contents outline"
    And I see h1 span element with title "TOCO 1"

  @javascript
  Scenario: Assert cache invalidation of the TOC Collection.
    And the following "custom_page" content:
      | title           | collection     | topic |
      | TOCO cached 1   | TOC Collection | HR    |
      | TOCO cached 1-1 | TOC Collection | HR    |
    And the following custom pages navigation menu structure for the "TOC Collection" collection:
      | title           | parent        | weight |
      | TOCO cached 1   |               | 1      |
      | TOCO cached 1-1 | TOCO cached 1 | 1      |
    When I visit the "TOCO cached 1-1" "custom page"
    Then I should see the link "Up" in the "Table of contents outline"
    When I am logged in as a facilitator of the "TOC Collection" collection
    And I go to the "TOC Collection" collection
    And I click the contextual link "Edit menu" in the "Header" region
    And I drag the "TOCO cached 1-1" table row to the left
    And I press "Save"
    And I visit the "TOCO cached 1-1" "custom page"
    Then I should not see the link "Up" in the "Table of contents outline"
