@api @group-h
Feature:
  Access and functionality checks for carousel paragraphs in custom pages.

  Background:
    Given the following "collection" content:
      | title       | state     | topic |
      | Roman ruins | published | HR    |

  @javascript
  Scenario: Test that carousels with specific content is working correctly.
    Given the following "collection" content:
      | title  | state     |
      | Sparta | published |
    And the following "custom_page" content:
      | title       | collection  | content       | state     | abstract             |
      | Some page 1 | Roman ruins | Some content. | published | Some page 1 abstract |
      | Some page 2 | Sparta      | Some content. | published | Some page 2 abstract |
      | Some page 3 | Sparta      | Some content. | published | Some page 3 abstract |

    When I am logged in as a moderator
    And I go to the homepage
    And I go to the "Roman ruins" collection
    And I click "Add custom page" in the plus button menu

    When I fill in the following:
      | Title    | Pages carousel          |
      | Abstract | Abstract pages carousel |
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a component to section 1
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
  Scenario: The empty-filter validation error stays within the Content filter section and the carousel can still be saved once a filter is added.
    # Regression test for ISAICP-11396.
    Given the following "custom_page" content:
      | title           | collection  | content       | state     | abstract |
      | Carousel target | Roman ruins | Some content. | published | Abstract |

    When I am logged in as a moderator
    And I go to the "Roman ruins" collection
    And I click "Add custom page" in the plus button menu
    And I fill in the following:
      | Title    | Carousel filter error scope |
      | Abstract | Abstract                    |
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a component to section 1
    And a modal should open
    And I click "Carousel"
    And I press "Save" in the "Modal buttons" region

    Then I should see the inline form error message "You need to add a filter in the Content listing block"
    And the "Global search" field should not have an inline form error
    And the "Allow shared content" field should not have an inline form error
    And the "Show content" field should not have an inline form error

    When I select "Custom page" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Custom page" with "Carousel target"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"

    Then I should see the "Carousel target" tile

  @javascript
  Scenario: Facilitators can see and edit the Content filter section of a carousel even when query_presets is set.
    # Regression: facilitators saw an empty Edit Carousel modal when
    # query_presets was set on the paragraph.
    Given the following "custom_page" content:
      | title              | collection  | state     |
      | Facilitator edit C | Roman ruins | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Facilitator edit C" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "Facilitator edit C" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I am logged in as a facilitator of the "Roman ruins" collection
    And I go to the edit form of the "Facilitator edit C" "custom page"
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout

    Then I should see "Show related content"
    And I should see "Content filter"
    And I should see the button "Add and configure filter"

  @javascript
  Scenario: Carousel's control buttons should be visible if there is more content to show.
    Given the following "collection" content:
      | title  | state     |
      | Sparta | published |
    And the following "news" content:
      | title       | headline    | collection | body          | state     |
      | News page 1 | Some page 1 | Sparta     | Some content. | published |
      | News page 2 | Some page 2 | Sparta     | Some content. | published |
      | News page 3 | Some page 3 | Sparta     | Some content. | published |
    And the following "custom_page" content:
      | title             | collection | state     |
      | Carousel controls | Sparta     | published |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "Carousel controls" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    Then I go to the "Carousel controls" custom_page
    When the "Previous item" button in the Content region should not be visible
    And the "Next item" button in the Content region should not be visible

    And the following "news" content:
      | title       | headline    | collection | body          | state     |
      | News page 4 | Some page 1 | Sparta     | Some content. | published |
      | News page 5 | Some page 2 | Sparta     | Some content. | published |
    And I reload the page
    When the "Previous item" button in the Content region should be visible
    And the "Next item" button in the Content region should be visible
