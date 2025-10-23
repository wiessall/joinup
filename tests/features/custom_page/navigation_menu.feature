@api @group-d
Feature: Navigation menu for custom pages
  In order to determine the order and visibility of custom pages in the navigation menu
  As a facilitator
  I need to be able to manage the navigation menu

  Scenario Outline: Access the navigation menu through the contextual link
    Given <group> content:
      | title          |
      | Rainbow tables |
    When I am logged in as a facilitator of the "Rainbow tables" <group>
    And I go to the homepage of the "Rainbow tables" <group>
    Then the navigation menu of the "Rainbow tables" <group> should have <visible items 1> visible items
    And I should see the contextual link "Edit menu" in the "Header" region
    But I should not see the contextual link "Add link" in the "Header" region

    # When we create a custom page it should automatically show up in the menu.
    When I click the contextual link "Add new page" in the "Header" region
    And I fill in the following:
      | Title    | About us |
      | Abstract | Abstract |
    And I select "Demography" options from "Topic"
    And I press "Publish"
    Then I should see the success message "Custom page About us has been created."
    And the navigation menu of the "Rainbow tables" <group> should have <visible items 2> visible items

    When I click the contextual link "Edit menu" in the "Header" region
    Then the navigation menu of the "Rainbow tables" <group> should have 4 items

    # When all pages are disabled, a message should be shown.
    When I disable "Overview" in the navigation menu of the "Rainbow tables" <group>
    And I disable "About us" in the navigation menu of the "Rainbow tables" <group>
    And I go to the homepage of the "Rainbow tables" <group>
    Then I should see the text "All the pages have been disabled for this <group>. You can edit the menu configuration or add a new page."
    And I should see the contextual link "Edit menu" in the "Header" region

    # The contextual menu can be used to navigate to the menu edit page.
    When I click the contextual link "Edit menu" in the "Header" region
    Then I should see the heading "Edit navigation menu of the Rainbow tables <group>"

    # The form to add a new menu link should not be accessible by anyone.
    And I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>

    # Test as a normal member of the group.
    Given I am logged in as a member of the "Rainbow tables" <group>
    When I go to the homepage of the "Rainbow tables" <group>
    # Members should not have access to the administration pages.
    Then I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>
    And I should not see the contextual link "Edit menu" in the "Header" region
    And I should not see the contextual link "Add new page" in the "Header" region

    # Test as a moderator.
    Given I am logged in as a moderator
    When I go to the homepage of the "Rainbow tables" <group>
    # Even moderators should not have access to the administration pages.
    Then I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>

    # Test as an anonymous user.
    Given I am an anonymous user
    When I go to the homepage of the "Rainbow tables" <group>
    # Anonymous users should definitely not have access to the administration pages.
    Then I should not have access to the menu link administration pages for the navigation menu of the "Rainbow tables" <group>
    And I should not see the contextual link "Edit menu" in the "Header" region
    And I should not see the contextual link "Add new page" in the "Header" region

    Examples:
      | group      | visible items 1 | visible items 2 |
      | collection | 3               | 4               |
      | solution   | 2               | 3               |

  @javascript
  Scenario: Menus with items that do not fit in available space should be wrapped in a dropdown
    Given collection content:
      | title         |
      | Plaster molds |
    And custom_page content:
      | title              | collection    | topic |
      | Molds for candles  | Plaster molds | HR    |
      | Molds for walls    | Plaster molds | HR    |
      | Molds for crafts   | Plaster molds | HR    |
      | Molds for pottery  | Plaster molds | HR    |
      | Molds for ceramics | Plaster molds | HR    |
      | Molds for soaps   | Plaster molds | HR    |
    When I go to the "Plaster molds" collection
    Then I should see the link "Molds for pottery" in the "Navigation menu"
    But I should not see the text "See more" in the "Navigation menu"
    Given custom_page content:
      | title           | collection    | topic |
      | Vintage molds   | Plaster molds | HR    |
      | Plaster casting | Plaster molds | HR    |
    When I reload the page
    Then I should see the text "See more" in the "Navigation menu"
    But I should not see the text "Plaster casting" in the "Navigation menu"
    When I press "See more"
    Then I should see the text "Plaster casting" in the "Navigation menu"

  @javascript
  Scenario: The contextual links button in the navigation menu should be always visible
    Given collection content:
      | title        |
      | Prism Gazers |
    And custom_page content:
      | title           | collection   | topic |
      | Mists of dreams | Prism Gazers | HR    |
    When I am logged in as a facilitator of the "Prism Gazers" collection
    And I go to the homepage of the "Prism Gazers" collection
    Then I should see the contextual links button in the "Navigation menu"
    And the "Edit menu" link in the "Navigation menu" should not be visible
    When I click the contextual links button in the "Navigation menu"
    Then the "Edit menu" link in the "Navigation menu" should be visible
    When I click the contextual links button in the "Navigation menu"
    Then the "Edit menu" link in the "Navigation menu" should not be visible

  Scenario: Synchronize titles of custom pages and menu links
    Given collection content:
      | title         |
      | Alphabeavers  |
    And custom_page content:
      | title       | collection   | topic |
      | Tree eaters | Alphabeavers | HR    |
    When I am logged in as a facilitator of the "Alphabeavers" "collection"
    And I go to the homepage of the "Alphabeavers" collection
    Then I should see the link "Tree eaters" in the "Navigation menu"
    When I click "Tree eaters"
    And I click "Edit" in the "Entity actions" region
    And I fill in "Title" with "Furry machines"
    And I press "Update"
    And I go to the homepage of the "Alphabeavers" collection
    Then I should see the link "Furry machines" in the "Navigation menu"

  Scenario: Only the links below the topmost page are rendered in TOC.
    Given collection content:
      | title     |
      | TOC Group |
    And custom_page content:
      | title       | collection | topic |
      | Page 1      | TOC Group  | HR    |
      | Page 2      | TOC Group  | HR    |
      | Subpage 1-1 | TOC Group  | HR    |
      | Subpage 1-2 | TOC Group  | HR    |
      | Subpage 2-1 | TOC Group  | HR    |
    And the following custom pages navigation menu structure for the "TOC Group" collection:
      | title       | parent | weight |
      | Page 1      |        | 1      |
      | Page 2      |        | 2      |
      | Subpage 1-1 | Page 1 | 1      |
      | Subpage 1-2 | Page 1 | 2      |
      | Subpage 2-1 | Page 2 | 1      |
    When I am logged in as a member of the "TOC Group" collection
    And I visit the "Page 1" "custom page"
    Then I should see the link "Subpage 1-1" in the "Table of contents"
    And I should see the link "Subpage 1-2" in the "Table of contents"
    But I should not see the link "Page 2" in the "Table of contents"
    And I should not see the link "Subpage 2-1" in the "Table of contents"

  @javascript
  Scenario: Only custom page entries can be nested in the navigation menu.
    Given collection content:
      | title     |
      | Backpacks |
    And custom_page content:
      | title      | collection | topic |
      | Types      | Backpacks  | HR    |
      | Frameless  | Backpacks  | HR    |
      | Bodypack   | Backpacks  | HR    |
    And the following custom pages navigation menu structure for the "Backpacks" collection:
      | title     | parent | weight |
      | Types     |        | 3      |
      | Frameless | Types  | 4      |
    When I am logged in as a facilitator of the "Backpacks" collection
    And I go to the "Backpacks" collection
    And I click the contextual link "Edit menu" in the "Header" region
    When I drag the "Frameless" table row to the left
    Then the draggable menu table of the "Backpacks" collection should be:
      | title       | parent |
      | Overview    |        |
      | Discussions |        |
      | Glossary    |        |
      | Bodypack    |        |
      | Types       |        |
      | Frameless   |        |
    # Links that don't refer to a node cannot be nested.
    When I drag the "Overview" table row to the right
    Then the draggable menu table of the "Backpacks" collection should be:
      | title       | parent |
      | Overview    |        |
      | Discussions |        |
      | Glossary    |        |
      | Bodypack    |        |
      | Types       |        |
      | Frameless   |        |

  Scenario: Show appropriate menu entries in the table of contents outline.
    Given collection content:
      | title        |
      | TOC Outline  |
    And custom_page content:
      | title    | collection  | state     | topic |
      | TOCO 1   | TOC Outline | published | HR    |
      | TOCO 1-1 | TOC Outline | published | HR    |
      | TOCO 1-2 | TOC Outline | published | HR    |
    And the following custom pages navigation menu structure for the "TOC Outline" collection:
      | title    | parent | weight |
      | TOCO 1   |        | 1      |
      | TOCO 1-1 | TOCO 1 | 1      |
      | TOCO 1-2 | TOCO 1 | 2      |
    When I am logged in as a member of the "TOC Outline" collection
    And I visit the "TOCO 1" "custom page"
    Then I should not see the link "Up" in the "Table of contents outline"
    When I click "TOCO 1-1" in the "Table of contents outline"
    Then I should see the link "Up" in the "Table of contents outline"
    When I click "Up" in the "Table of contents outline"
    And I see h1 span element with title "TOCO 1"

  @javascript
  Scenario: Assert cache invalidation of the TOC outline.
    Given collection content:
      | title       |
      | TOC Cached  |
    And custom_page content:
      | title           | collection | topic |
      | TOCO cached 1   | TOC Cached | HR    |
      | TOCO cached 1-1 | TOC Cached | HR    |
    And the following custom pages navigation menu structure for the "TOC Cached" collection:
      | title           | parent        | weight |
      | TOCO cached 1   |               | 1      |
      | TOCO cached 1-1 | TOCO cached 1 | 1      |
    When I visit the "TOCO cached 1-1" "custom page"
    Then I should see the link "Up" in the "Table of contents outline"
    When I am logged in as a facilitator of the "TOC Cached" collection
    And I go to the "TOC Cached" collection
    And I click the contextual link "Edit menu" in the "Header" region
    And I drag the "TOCO cached 1-1" table row to the left
    And I press "Save"
    And I visit the "TOCO cached 1-1" "custom page"
    Then I should not see the link "Up" in the "Table of contents outline"

  @javascript
  Scenario: Facilitator sees and uses edit links for custom pages in group menu
    Given collection content:
      | title         |
      | Autumn Leaves |
    And custom_page content:
      | title      | collection    |
      | Typewriter | Autumn Leaves |
    When I am logged in as a facilitator of the "Autumn Leaves" collection
    And I go to the "Autumn Leaves" collection
    And I click the contextual link "Edit menu" in the "Header" region
    Then the "group menu edit table" table should contain the following columns:
      | Page       | Operations  |
      | Overview   | Edit        |
      | Typewriter | Edit Delete |
    When I click "Edit" in the "Typewriter" row
    Then I should see the heading "Edit Custom page Typewriter"
