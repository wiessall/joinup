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
    And news content:
      | title          | headline       | state     | collection      | body | content |
      | Archived news  | Archived news  | archived  | Some collection | Test | Test    |
      | Published news | Published news | validated | Some collection | Test | Test    |

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

    When I go to the "Some page" custom page
    And I click "Edit"
    And I select "Archived" from "Show archived content"
    And I press "Save"
    Then I should see the heading "Some page"
    And I should see the "Archived news" tile
    But I should not see the "Published news" tile

    When I go to the "Some page" custom page
    And I click "Edit"
    And I select "All" from "Show archived content"
    And I press "Save"
    Then I should see the heading "Some page"
    And I should see the "Archived news" tile
    And I should see the "Published news" tile

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
