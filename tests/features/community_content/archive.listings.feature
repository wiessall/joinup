@api @group-g @terms
Feature: Archived content on content listings - groups and paragraphs.

  Background:
    Given owner:
      | name       |
      | Some owner |
    And contact:
      | name | Some contact |
    And collections:
      | title           | description | topic             | owner      | contact information | state     |
      | Some collection | Test        | Supplier exchange | Some owner | Some contact        | validated |
      | Poly screens    | Test        | Supplier exchange | Some owner | Some contact        | validated |
    And news content:
      | title          | headline       | state     | collection      | body | content |
      | Archived news  | Archived news  | validated | Some collection | Test | Test    |
      | Published news | Published news | validated | Some collection | Test | Test    |
      | Poly news 1    | Poly news 1    | validated | Poly screens    | Test | Test    |
      | Poly news 2    | Poly news 2    | validated | Poly screens    | Test | Test    |
      | Poly news 3    | Poly news 3    | validated | Poly screens    | Test | Test    |
      | Poly news 4    | Poly news 4    | validated | Poly screens    | Test | Test    |
    # We need the "Archived news" content to be archived by altering its state
    # because "Unarchiving" it restored the previous revision and thus, needs
    # to have a previous revision (created as validated).
    And the workflow state of the "Archived news" content is set to "archived"

  Scenario: Collection content listing can control archived content visibility.
    When I am logged in as a moderator
    And I go to the edit form of the "Some collection" collection
    And I check "Enable the search field"
    And I press "Publish"
    Then I should see the heading "Some collection"
    # By default, 'Show archived content' is set to 'unarchived'.
    And I should see the "Published news" tile
    But I should not see the "Archived news" tile

    When I go to the edit form of the "Some collection" collection
    And I select "Archived" from "Show archived content"
    And I press "Publish"
    Then I should see the heading "Some collection"
    And I should see the "Archived news" tile
    But I should not see the "Published news" tile

    When I go to the edit form of the "Some collection" collection
    And I select "All" from "Show archived content"
    And I press "Publish"
    Then I should see the heading "Some collection"
    And I should see the "Published news" tile
    # By default, the toggle to show archived content is disabled.
    But I should not see the "Archived news" tile

  Scenario: Content listing paragraph can control visibility of archived items.
    When I am logged in as a moderator
    And I go to the "Some collection" collection
    And I click "Add custom page"
    And I fill in "Title" with "Some page"
    And I press "Add Content listing"
    And I fill in "Query presets" with "entity_bundle|news"
    And I press "Save"
    Then I should see the heading "Some page"
    # By default, 'Show archived content' is set to 'unarchived'.
    And I should see the "Published news" tile
    But I should not see the "Archived news" tile

    And I go to the "Poly screens" collection
    And I click "Add custom page"
    And I fill in "Title" with "Poly page"
    And I press "Add Content listing"
    And I select "All" from "Show archived content"
    And I fill in "Query presets" with "entity_bundle|news"
    And I press "Save"

    When I go to the "Some page" custom page
    And I click "Edit"
    And I select "Archived" from "Show archived content"
    And I press "Save"
    Then I should see the heading "Some page"
    And I should see the "Archived news" tile
    But I should not see the "Published news" tile

    When I go to the "Poly page" custom page
    Then I should see the heading "Poly page"
    And I should not see the text "Show archived"

    When I go to the "Some page" custom page
    And I click "Edit"
    And I select "All" from "Show archived content"
    And I press "Save"
    Then I should see the heading "Some page"
    Then I should see the following facet items "News, Show archived" in this order
    And I should see the text "Show archived"
    And I should not see the "Archived news" tile
    And I should see the "Published news" tile

    When I click "Show archived"
    And I should see the "Archived news" tile
    And I should not see the "Published news" tile

    When I go to the "Poly page" custom page
    Then I should see the heading "Poly page"
    And I should not see the text "Show archived"

    When I go to the "Some page" custom page
    And I click "Edit"
    And I select "Archived" from "Show archived content"
    And I press "Save"
    Then I should see the heading "Some page"
    And I should not see the text "Show archived"
    And I should see the "Archived news" tile
    And I should not see the "Published news" tile

    When I go to the "Poly page" custom page
    Then I should see the heading "Poly page"
    And I should not see the text "Show archived"

    When I go to the "Some page" custom page
    And I click "Edit"
    And I select "Unarchived" from "Show archived content"
    And I press "Save"
    Then I should see the heading "Some page"
    And I should not see the text "Show archived"
    And I should not see the "Archived news" tile
    And I should see the "Published news" tile

    When I go to the "Poly page" custom page
    Then I should see the heading "Poly page"
    And I should not see the text "Show archived"

  Scenario: Carousel listings can also control archived items visibility.
    Given collection:
      | uri   | http://data.europa.eu/w21/8e30f798-ff2b-478b-9c09-5ed5a63b4c8c |
      | title | Interoperable Europe                                           |
      | state | validated                                                      |
    And news content:
      | title              | headline           | state     | collection           | body | content |
      | IOP archived news  | IOP archived news  | archived  | Interoperable Europe | Test | Test    |
      | IOP published news | IOP published news | validated | Interoperable Europe | Test | Test    |

    When I am logged in as a moderator
    And I go to the "Interoperable Europe" collection
    And I click "Add custom page"
    And I fill in "Title" with "IOP page"
    And I press "Add Row"
    And I press "Add 1 column layout"
    And I press "Add Carousel"
    And I fill in "Query presets" with "entity_bundle|news"
    And I press "Save"
    Then I should see the heading "IOP page"
    # By default, 'Show archived content' is set to 'unarchived'.
    And I should see the "IOP published news" tile
    But I should not see the "IOP archived news" tile

    When I go to the "IOP page" custom page
    And I click "Edit"
    And I select "Archived" from "Show archived content"
    And I press "Save"
    Then I should see the heading "IOP page"
    And I should see the "IOP archived news" tile
    But I should not see the "IOP published news" tile

    When I go to the "IOP page" custom page
    And I click "Edit"
    And I select "All" from "Show archived content"
    And I press "Save"
    Then I should see the heading "IOP page"
    And I should see the "IOP archived news" tile
    And I should see the "IOP published news" tile

  Scenario: "Show archived" facet does not show up when there are no archived items.
    Given the "Some collection" collection is configured to show "All" community content in the overview
    When I go to the "Some collection" collection
    Then I should see the "collection content archived toggle" facet

    When I am logged in as a moderator
    And I go to the edit form of the "Archived news" news
    And I press "Unarchive"
    Then I should see the heading "Archived news"

    And I go to the "Some collection" collection
    # @todo Fix this in ISAICP-7756.
    # Then I should see the heading "Some collection"
    And I should see the "Published news" tile
    And I should see the "Archived news" tile
    But I should not see the "collection content archived toggle" facet

    When I go to the edit form of the "Archived news" news
    And I press "Archive"
    And I go to the "Some collection" collection
    Then I should see the "Published news" tile
    But I should not see the "Archived news" tile
    And I should see the "collection content archived toggle" facet

  Scenario: "Show archived" facet on Discussions page content listing.
    Given collections:
      | title                      | description | topic             | owner      | contact information | state     |
      | Some discussion collection | Test        | Supplier exchange | Some owner | Some contact        | validated |
    And discussion content:
      | title           | collection                 | state     |
      | Rand discussion | Some discussion collection | validated |
      | Pest discussion | Some discussion collection | archived  |
      | Poll discussion | Some discussion collection | validated |
      | Moon discussion | Some discussion collection | archived  |
      | Ruin discussion | Some discussion collection | validated |

    When I go to the homepage of the "Some discussion collection" collection
    And I should see the text "Discussions" in the "Navigation menu"

    When I click "Discussions"
    Then I should see the "Rand discussion" tile
    And I should see the "Poll discussion" tile
    And I should see the "Ruin discussion" tile
    And I should not see the "Moon discussion" tile
    And I should not see the "Pest discussion" tile

    When I click "Show archived"
    And I should see the "Pest discussion" tile
    And I should see the "Moon discussion" tile
    And I should not see the "Rand discussion" tile
    And I should not see the "Poll discussion" tile
    And I should not see the "Ruin discussion" tile
