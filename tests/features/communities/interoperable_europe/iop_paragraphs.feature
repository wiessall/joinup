@api @group-b
Feature:
  Access and functionality checks for the IOP related paragraphs.

  Background:
    # Mimic the "Interoperable Europe" collection.
    Given collection content:
      | uri                                                            | title                | topic      |
      | http://data.europa.eu/w21/8e30f798-ff2b-478b-9c09-5ed5a63b4c8c | Interoperable Europe | Demography |

  Scenario: Test that carousels content listing options are working correctly.
    Given collection content:
      | title       |
      | Carousel c1 |
    And news content:
      | title       | collection           | body          |
      | Some news 1 | Interoperable Europe | Some content. |
      | Some news 2 | Carousel c1          | Come content. |
    And custom_page content:
      | title         | collection           |
      | News carousel | Interoperable Europe |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "News carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "News carousel" custom_page
    # Ensure that listings are restricted only for group content by default.
    Then I should see the "Some news 1" tile
    But I should not see the "Some news 2" tile

  @javascript
  Scenario: Test that carousels with specific content is working correctly.
    Given collection content:
      | title       |
      | Carousel c1 |
    And custom_page content:
      | title       | collection           | body          |
      | Some page 1 | Interoperable Europe | Some content. |
      | Some page 2 | Carousel c1          | Some content. |
      | Some page 3 | Carousel c1          | Some content. |

    When I am logged in as a moderator
    And I go to the "Interoperable Europe" collection
    And I click "Add custom page" in the plus button menu

    When I fill in the following:
      | Title    | Pages carousel          |
      | Abstract | Abstract pages carousel |
    And I select "Demography" options from "Topic"
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

  Scenario: Carousels should be ordered by creation date by default.
    Given news content:
      | title       | collection           | body          | created    |
      | Some news 1 | Interoperable Europe | Some content. | 2 day ago  |
      | Some news 2 | Interoperable Europe | Some content. | 3 days ago |
      | Some news 3 | Interoperable Europe | Some content. | 1 days ago |
    And custom_page content:
      | title                | collection           |
      | A carousel with news | Interoperable Europe |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "A carousel with news" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "A carousel with news" custom_page
    Then I should see the following tiles in the correct order:
      | Some news 3 |
      | Some news 1 |
      | Some news 2 |

  Scenario: Test also that events, when listed alone, are sorted by event date.
    Given event content:
      | title        | collection           | body          | created    | start date | end date   |
      | Some event 1 | Interoperable Europe | Some content. | 2 day ago  | 5 day ago  | 4 day ago  |
      | Some event 2 | Interoperable Europe | Some content. | 3 days ago | 6 days ago | 5 days ago |
      | Some event 3 | Interoperable Europe | Some content. | 1 days ago | 7 days ago | 6 days ago |
    And custom_page content:
      | title           | collection           |
      | Events carousel | Interoperable Europe |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "Events carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:19:"entity_bundle\|event";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "Events carousel" custom_page
    Then I should see the following tiles in the correct order:
      | Some event 1 |
      | Some event 2 |
      | Some event 3 |
