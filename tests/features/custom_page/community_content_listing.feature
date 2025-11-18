@api @group-d
Feature:
  In order to make it easy to browse to specific content
  As a moderator
  As a facilitator
  I need to be able to configure community content listing

  Background:
    Given user:
      | Username | jimmy7892                       |
      | E-mail   | jimmy.nine@ec-europa.example.eu |
    And collection content:
      | title      | logo     |
      | Nintendo64 | logo.png |
    And news content:
      | title                                 | collection | field_paragraphs_body       |
      | Rare Nintendo64 disk drive discovered | Nintendo64 | Magnetic drive called 64DD. |
    And discussion content:
      | title                           | collection | content                 |
      | What's your favourite N64 game? | Nintendo64 | Post title and reasons. |
    And the following collection user membership:
      | collection | user      | roles       |
      | Nintendo64 | jimmy7892 | facilitator |

  @javascript
  Scenario: Configure content listing with filters and reordering
    Given I am logged in as jimmy7892
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    Then I should see the heading "Add custom page"
    When I fill in "Title" with "Chosen content"
    And I fill in "Abstract" with "Abstract chosen content"
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I select "Discussion" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Discussion" with "What's your favourite N64 game?"
    And I select "News" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "News" with "Rare Nintendo64 disk drive discovered"
    And I press "Save" in the "Modal buttons" region
    And I press "Save as draft"
    Then I should see the heading "Chosen content"
    And I should see the following tiles in the correct order:
      | What's your favourite N64 game?       |
      | Rare Nintendo64 disk drive discovered |

    # Test reordering filters.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I drag the table row in the "Content listing field filter form" region at position 2 up
    And I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should see the following tiles in the correct order:
      | Rare Nintendo64 disk drive discovered |
      | What's your favourite N64 game?       |

  @javascript
  Scenario: Content type facets filter displayed items
    Given I am logged in as jimmy7892
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu

    Then I should see the heading "Add custom page"
    When I fill in the following:
      | Title    | Collection content          |
      | Abstract | Abstract collection content |
    And I select "Demography" options from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I select "Discussion" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Discussion" with "What's your favourite N64 game?"
    And I select "News" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "News" with "Rare Nintendo64 disk drive discovered"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"
    Then I should see the following facet items "Discussion, News" in this order
    When I click the News content tab
    Then I should see the "Rare Nintendo64 disk drive discovered" tile
    And I should not see the heading "What's your favourite N64 game?"

  Scenario: Test listing by keywords
    Given document content:
      | title        | keywords           | field_paragraphs_body | collection |
      | User's Guide | nintendo64 manuals | User manual           | Nintendo64 |
    And custom_page content:
      | title   | collection |
      | Manuals | Nintendo64 |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "Manuals" title with:
    """
    a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:60:"entity_bundle|document
            keywords|"nintendo64 manuals"";s:5:"limit";s:2:"10";}
    """
    When I go to the "Manuals" custom_page
    Then I should see the "User's Guide" tile

  @javascript
  Scenario: Empty filter validation error
    Given I am logged in as jimmy7892
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    And I fill in "Title" with "Empty filter test"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I press "Save" in the "Modal buttons" region
    Then I should see the inline form error message "You need to add a filter in the Content listing block"

  @javascript
  Scenario: Regression test for ISAICP-7040 - Multiple content listing paragraphs maintain separate state
    Given solution content:
      | title                | collection |
      | Save the Planet      | Nintendo64 |
      | Fight Global Warming | Nintendo64 |
    And custom_page content:
      | title   | collection |
      | Forests | Nintendo64 |
    And I am logged in as jimmy7892
    And I go to the edit form of the "Forests" "custom page"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I select "Solution" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Solution" with "Save the Planet"
    And I press "Save" in the "Modal buttons" region

    # Add second paragraph.
    When I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "Fight Global Warming"
    And I press "Save" in the "Modal buttons" region

    # Verify first paragraph maintains its state.
    And I "edit" from the "Custom page body" field the child 2 paragraph of 1 layout
    Then the 1st filter of the 1st content listing paragraph value is "Save the Planet"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the text "Forests"

  @javascript
  Scenario: Regression test for ISAICP-7183 - Remove filter updates listing correctly
    Given solution content:
      | title              | collection |
      | Fight battery mode | Nintendo64 |
      | Black Noise        | Nintendo64 |
    And custom_page content:
      | title | collection | abstract |
      | Bikes | Nintendo64 | N/A      |
    And I am logged in as jimmy7892
    And I go to the edit form of the "Bikes" "custom page"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I select "Solution" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Solution" with "Fight battery mode"
    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "Black Noise"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "Bikes"

    # Verify filter removal works correctly.
    When I go to the edit form of the "Bikes" "custom page"
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I click "Remove filter" at 1st filter of the 1st content listing paragraph
    Then the 1st filter of the 1st content listing paragraph value is "Black Noise"

  Scenario: Regression test for ISAICP-7819 - Pagination with multiple content listing paragraphs
    Given event content:
      | title              | collection | start date | end date   |
      | Event pagination 1 | Nintendo64 | now +8 day | now +9 day |
      | Event pagination 2 | Nintendo64 | now +7 day | now +8 day |
      | Event pagination 3 | Nintendo64 | now +6 day | now +7 day |
    And news content:
      | title             | collection |
      | News pagination 1 | Nintendo64 |
      | News pagination 2 | Nintendo64 |
      | News pagination 3 | Nintendo64 |
      | News pagination 4 | Nintendo64 |
    And custom_page content:
      | title          | collection |
      | Pagination bug | Nintendo64 |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Pagination bug" title:
      | content_listing:value | a:7:{s:6:"fields";a:2:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}s:22:"content_listing_status";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:1:"2";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Pagination bug" title:
      | content_listing:value | a:7:{s:6:"fields";a:2:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}s:22:"content_listing_status";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:19:"entity_bundle\|event";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:1:"2";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "Pagination bug" custom_page
    And I click "Go to page 2"
    Then I should see the tiles in the correct order:
      | News pagination 2   |
      | News pagination 3   |
      | Event pagination 1  |
      | Event pagination 2  |

  @javascript
  Scenario: Facets are hidden with multiple content listing paragraphs
    Given custom_page content:
      | title                  | collection |
      | Multi paragraph facets | Nintendo64 |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Multi paragraph facets" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:38:"entity_bundle\|discussion,event,news\|IN";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:1;} |

    And I go to the "Multi paragraph facets" custom_page
    Then I should see the following facet items "Discussion, News" in this order

    Given I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Multi paragraph facets" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:38:"entity_bundle\|discussion,event,news\|IN";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:1;} |
    And I go to the "Multi paragraph facets" custom_page
    Then I should not see the following facet items "Discussion, News"
