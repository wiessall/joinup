@api @group-h
Feature:
  Access and functionality checks for carousel paragraphs in custom pages.

  Background:
    Given collection:
      | title | Roman ruins |
      | state | published   |
      | topic | HR          |

  @javascript
  Scenario: Test that carousels with specific content is working correctly.
    Given the following collections:
      | title  | state     |
      | Sparta | published |
    And custom_page content:
      | title       | headline    | collection  | content       | state     | abstract             |
      | Some page 1 | Some page 1 | Roman ruins | Some content. | published | Some page 1 abstract |
      | Some page 2 | Some page 2 | Sparta      | Some content. | published | Some page 2 abstract |
      | Some page 3 | Some page 3 | Sparta      | Some content. | published | Some page 3 abstract |

    When I am logged in as a moderator
    And I go to the homepage
    And I go to the "Roman ruins" collection
    And I click "Add custom page" in the plus button menu

    When I fill in the following:
      | Title    | Pages carousel          |
      | Abstract | Abstract pages carousel |
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Carousel"
    And I select "Custom page" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Custom page" with "Some page 1"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"

    Then I should see the "Some page 1" tile
    But I should not see the "Some page 2" tile
    And I should not see the "Some page 3" tile

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Custom page" with "Some page 3"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"

    # Global search is not checked. No tiles should be shown.
    Then I should not see the "Some page 1" tile
    And I should not see the "Some page 2" tile
    And I should not see the "Some page 3" tile

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I check "Global search"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"

    Then I should see the "Some page 3" tile
    But I should not see the "Some page 1" tile
    And I should not see the "Some page 2" tile

  @javascript
  Scenario: Carousel's control buttons should be visible if there is more content to show.
    Given the following collections:
      | title  | state     |
      | Sparta | published |
    And news content:
      | title       | headline    | collection | body          | state     |
      | News page 1 | Some page 1 | Sparta     | Some content. | published |
      | News page 2 | Some page 2 | Sparta     | Some content. | published |
      | News page 3 | Some page 3 | Sparta     | Some content. | published |
    And custom_page content:
      | title             | collection | state     |
      | Carousel controls | Sparta     | published |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "Carousel controls" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    Then I go to the "Carousel controls" custom page
    When the "Previous item" button in the Content region should not be visible
    And the "Next item" button in the Content region should not be visible

    Then news content:
      | title       | headline    | collection | body          | state     |
      | News page 4 | Some page 1 | Sparta     | Some content. | published |
      | News page 5 | Some page 2 | Sparta     | Some content. | published |
    And I go to the "Carousel controls" custom page
    When the "Previous item" button in the Content region should be visible
    And the "Next item" button in the Content region should be visible
