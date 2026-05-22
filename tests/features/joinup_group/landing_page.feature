@api @group-e
Feature: Tests the group landing page customization.

  Scenario Outline:
  """
  - As an anonymous, when I visit a group, I cannot set its landing page.
  - As a user, when I visit a group, I cannot set its landing page.
  - As a group member, when I visit a group, I cannot set its landing page.
  - As a group facilitator, when I visit other group, I cannot set its landing page.
  - As a moderator, when I visit a group, I can set a landing page.
  - As a group facilitator, when I visit my group, I cannot set a landing page.
  """

    Given the following "<group>" content:
      | title               | description                         |
      | <group> group       | The <group> arbitrary description   |
      | other <group> group | Other <group> arbitrary description |
    And the following "custom_page" content:
      | title         | <group>       | body                      |
      | Custom Page 1 | <group> group | The custom page 1 content |
      | Custom Page 2 | <group> group | The custom page 2 content |
      | Custom Page 3 | <group> group | The custom page 3 content |
    Given I am not logged in
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"
    And I should see the following navigation menu items in the specified order:
      | text          |
      | Overview      |
      | Custom Page 1 |
      | Custom Page 2 |
      | Custom Page 3 |

    Given I am logged in as an authenticated
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"

    Given I am logged in as a member of the "<group> group" <group>
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"

    Given I am logged in as a facilitator of the "other <group> group" <group>
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"

    Given I am logged in as a facilitator of the "<group> group" <group>
    When I go to the homepage of the "<group> group" <group>
    Then I should see the heading "<group> group"
    And I should see "The <group> arbitrary description"
    Then I should see the link "Settings"
    When I click "Settings"
    Then the following fields should not be present "Landing page"

    Given I am logged in as a moderator
    When I go to the homepage of the "<group> group" <group>
    Then I should see the heading "<group> group"
    Then I should see the link "Settings"
    And I should not see the link "Edit landing page"
    When I click "Settings"
    Then the following fields should be present "Landing page, Overview label"
    And the "Landing page" select should contain the following options:
      | - Standard landing page - |
      | Custom Page 1             |
      | Custom Page 2             |
      | Custom Page 3             |
    When I select "Custom Page 3" from "Landing page"
    And I fill in "Overview label" with "Welcome"
    And I press "Save"
    Then I should see "<group> group"
    And I should see the heading "Custom Page 3"
    And I should see "The custom page 3 content"
    But I should not see "The <group> arbitrary description"
    And I should not see "The custom page 2 content"
    And I should see the link "Edit landing page"
    And I should see the following navigation menu items in the specified order:
      | text          |
      | Welcome       |
      | Custom Page 1 |
      | Custom Page 2 |
      | Custom Page 3 |
    And "Welcome" should be the active item in the "Navigation menu" region
    When I disable "Welcome" in the navigation menu of the "<group> group" <group>
    And I go to the homepage of the "<group> group" <group>
    Then "Custom Page 3" should be the active item in the "Navigation menu" region
    And I enable "Welcome" in the navigation menu of the "<group> group" <group>

    # Regression test for ticket ISAICP-9253.
    When I click "Edit landing page"
    When I check the box "Hide title"
    And I press "Update"
    Then I go to the homepage of the "<group> group" <group>
    And the response should contain "page-title__title visually-hidden"
    Then I should see "<group> group"

    When I click "Settings"
    When I select "- Standard landing page -" from "Landing page"
    And I press "Save"
    Then I should see the heading "<group> group"
    And I should see "The <group> arbitrary description"
    And I should not see the heading "Custom Page 3"
    And I should not see "The custom page 2 content"
    And I should not see "The custom page 3 content"
    And I should not see the link "Edit landing page"

    Examples:
      | group      |
      | collection |
      | solution   |

  Scenario: Do not allow to unpublish or delete a custom page set as landing page
    Given the following "collection" content:
      | title          |
      | Rally fanatics |
      | My own book    |
    And the following "custom_page" content:
      | title         | collection     | body                      |
      | Custom Page 1 | Rally fanatics | The custom page 1 content |
      | Custom Page 2 | My own book    | The custom page 2 content |
    Given I am logged in as a moderator
    When I go to the homepage of the "Rally fanatics" collection
    And I click "Settings"
    And I select "Custom Page 1" from "Landing page"
    And I press "Save"
    And I go to the edit form of the "Custom Page 1" "custom page"
    And I click "Delete" in the "Entity actions" region
    Then I should see "You can not delete this landing page!"
    And I should see "This page acts as landing page for the collection. In order to delete it should be first unset as landing page."
    When I go to the "Custom Page 2" custom_page
    When I click "Delete" in the "Entity actions" region
    Then I should see "Are you sure you want to delete the content item Custom Page 2?"
    When I go to the edit form of the "Custom Page 1" "custom page"
    Then I should not see the button "Unpublish"
    When I go to the homepage of the "Rally fanatics" collection
    And I click "Settings"
    And I select "- Standard landing page -" from "Landing page"
    And I press "Save"
    And I go to the "Custom Page 1" custom_page
    And I click "Delete" in the "Entity actions" region
    Then I should not see "You can not delete this landing page!"
    And I should not see "This page acts as landing page for the collection. In order to delete it should be first unset as landing page."

  Scenario: Landing custom page redirects to group page.
    Given the following "collection" content:
      | title         |
      | United States |
    And the following "custom_page" content:
      | title    | collection    | body                                                                                 |
      | Michigan | United States | Michigan is a state in the Great Lakes region of the Upper Midwestern United States. |

    When I go to the "Michigan" custom_page
    Then I should be on "/collection/united-states/michigan"

    Given I am logged in as a moderator
    And I go to the homepage of the "United States" collection
    And I click "Settings"
    And I select "Michigan" from "Landing page"
    And I press "Save"

    When I go to the "Michigan" custom_page
    Then I should be on "/collection/united-states"

  Scenario: Active navigation link is correct on flat group navigation when visiting a nested custom page
    Given the following "collection" content:
      | title         |
      | Space Weavers |
    And the following "custom_page" content:
      | title     | collection    |
      | Andromeda | Space Weavers |
      | Orion     | Space Weavers |
    And the following custom pages navigation menu structure for the "Space Weavers" collection:
      | title | parent    | enabled |
      | Orion | Andromeda | no      |
    And I visit the "Andromeda" "custom page"
    And I should see the following navigation menu items in the specified order:
      | text      |
      | Overview  |
      | Andromeda |
    Then "Andromeda" should be the active items in the "Navigation menu" region
    When I visit the "Orion" "custom page"
    Then no items should be active in the "Navigation menu" region
