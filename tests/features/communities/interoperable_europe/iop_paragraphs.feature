@api
Feature:
  Access and functionality checks for the IOP related paragraphs.

  Background:
    # Mimic the "Interoperable Europe" collection.
    Given collection:
      | uri   | http://data.europa.eu/w21/8e30f798-ff2b-478b-9c09-5ed5a63b4c8c |
      | title | Interoperable Europe                                           |
      | state | validated                                                      |

  @javascript
  Scenario: Moderators can access the special paragraphs.
    Given collection:
      | title | Joinup    |
      | state | validated |

    Given I am logged in as a moderator
    And I go to the "Joinup" collection
    And I click "Add custom page"
    Then I should not see the button "Add Row"

    When I am on the homepage
    And I click "Interoperable Europe" in the "Navigation bar"
    And I click "Add custom page"
    # Fill in the title to avoid the CSS validation kicking in when clicking
    # buttons.
    And I fill in "Title" with "Hello world"
    Then I should see the button "Add Carousel"
    And I should see the button "Add Row"
    But I should not see the button "Add 1 column layout"
    And I should not see the button "Add 2 column layout"
    And I should not see the button "Add 2 column with featured media on the left"
    And I should not see the button "Add 2 column with featured media on the right"
    And I should not see the button "Add 3 column layout"
    And I should not see the button "Add Image"
    And I should not see the button "Add Call to action"
    And I should not see the button "Add Configurable listing"

    When I press "Add Row"
    Then I should see the button "Add 1 column layout"
    And I should see the button "Add 2 column layout"
    And I should see the button "Add 2 column with featured media on the left"
    And I should see the button "Add 2 column with featured media on the right"
    And I should see the button "Add 3 column layout"
    But I should not see the button "Add Image"
    And I should not see the button "Add Call to action"
    And the following fields should not be present "URL, Link text"
    And I should not see the button "Add Configurable listing"

    When I press "Add 1 column layout"
    Then I should not see the button "Add Configurable listing"
    But I should see the button "Add Image"
    And I should see the button "Add Call to action"
    And I should see the button "Add Carousel"
    When I press "Add Carousel"
    Then the following fields should be present "URL, Link text, Available filters"

    Given I am logged in as a facilitator of the "Joinup" collection
    And I go to the "Joinup" collection
    And I click "Add custom page"
    Then I should not see the button "Add Row"

    Given I am logged in as a facilitator of the "Interoperable Europe" collection
    And I am on the homepage
    And I click "Interoperable Europe" in the "Navigation bar"
    And I click "Add custom page"
    Then I should see the button "Add Row"
    When I press "Add Row"
    Then I should see the button "Add 1 column layout"
    And I should see the button "Add 2 column layout"
    And I should see the button "Add 2 column with featured media on the left"
    And I should see the button "Add 2 column with featured media on the right"
    And I should see the button "Add 3 column layout"

  Scenario: Test that carousels content listing options are working correctly.
    Given the following collections:
      | title       | state     |
      | Carousel c1 | validated |
    And news content:
      | title       | collection           | content       | state     |
      | Some news 1 | Interoperable Europe | Some content. | validated |
      | Some news 2 | Carousel c1          | Come content. | validated |
    And custom_page content:
      | title         | collection  | state     |
      | News carousel | Interoperable Europe | validated |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "News carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "News carousel" custom page
    # Ensure that listings are restricted only for group content by default.
    Then I should see the "Some news 1" tile
    But I should not see the "Some news 2" tile

  @javascript
  Scenario: Test that carousels with specific content is working correctly.
    Given the following collections:
      | title       | state     |
      | Carousel c1 | validated |
    And custom_page content:
      | title       | collection           | content       | state     |
      | Some page 1 | Interoperable Europe | Some content. | validated |
      | Some page 2 | Carousel c1          | Some content. | validated |
      | Some page 3 | Carousel c1          | Some content. | validated |

    When I am logged in as a moderator
    And I go to the homepage
    And I click "Interoperable Europe" in the "Navigation bar"
    And I click "Add custom page" in the plus button menu

    When I fill in the following:
      | Title | Pages carousel |
    And I click "Choose component"
    And I click "Carousel"
    And I select "Custom page" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Custom page" with "Some page 1"
    And I press "Save" in the "Dialog buttons" region
    And I press "Save"

    Then I should see the "Some page 1" tile
    But I should not see the "Some page 2" tile
    And I should not see the "Some page 3" tile

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I click "Edit" in the "Custom page body" for paragraph layout 1
    And I fill in "Custom page" with "Some page 3"
    And I press "Save" in the "Dialog buttons" region
    And I press "Save"

    # Global search is not checked. No tiles should be shown.
    Then I should not see the "Some page 1" tile
    And I should not see the "Some page 2" tile
    And I should not see the "Some page 3" tile

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I click "Edit" in the "Custom page body" for paragraph layout 1
    And I check "Global search"
    And I press "Save" in the "Dialog buttons" region
    And I press "Save"

    Then I should see the "Some page 3" tile
    But I should not see the "Some page 1" tile
    And I should not see the "Some page 2" tile

  Scenario: Carousels should be ordered by creation date by default.
    Given news content:
      | title       | collection           | content       | state     | created    |
      | Some news 1 | Interoperable Europe | Some content. | validated | 2 day ago  |
      | Some news 2 | Interoperable Europe | Some content. | validated | 3 days ago |
      | Some news 3 | Interoperable Europe | Some content. | validated | 1 days ago |
    And custom_page content:
      | title                | collection           | state     |
      | A carousel with news | Interoperable Europe | validated |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "A carousel with news" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "A carousel with news" custom page
    Then I should see the following tiles in the correct order:
      | Some news 3 |
      | Some news 1 |
      | Some news 2 |

  Scenario: Test also that events, when listed alone, are sorted by event date.
    Given event content:
      | title        | collection           | content       | state     | created    | start date | end date   |
      | Some event 1 | Interoperable Europe | Some content. | validated | 2 day ago  | 5 day ago  | 4 day ago  |
      | Some event 2 | Interoperable Europe | Some content. | validated | 3 days ago | 6 days ago | 5 days ago |
      | Some event 3 | Interoperable Europe | Some content. | validated | 1 days ago | 7 days ago | 6 days ago |
    And custom_page content:
      | title           | collection           | state     |
      | Events carousel | Interoperable Europe | validated |
    And I append "carousel" to "field_paragraphs_body" field in "node" entity with following "Events carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:19:"entity_bundle\|event";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "Events carousel" custom page
    Then I should see the following tiles in the correct order:
      | Some event 1 |
      | Some event 2 |
      | Some event 3 |
