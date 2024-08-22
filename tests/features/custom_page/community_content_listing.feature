@api @group-d
Feature:
  In order to make it easy to browse to specific content
  As a moderator
  I need to be able to configure community content listing

  Background:
    Given user:
      | Username    | jimmy7892                       |
      | First name  | Jimmy                           |
      | Family name | Nine                            |
      | E-mail      | jimmy.nine@ec-europa.example.eu |
    Given the following collections:
      | title      | logo     | state     |
      | Nintendo64 | logo.png | published |
      | Emulators  | logo.png | published |
    And custom_page content:
      | title               | collection | created           | body                               | state     | pinned |
      | Latest news listing | Nintendo64 | 2019-10-01 4:26am | You can find the latest news here. | published | no     |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Latest news listing" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |
    And news content:
      | title                                 | collection | created           | field_paragraphs_body            | state     | pinned |
      | Rare Nintendo64 disk drive discovered | Nintendo64 | 2018-10-01 4:26am | Magnetic drive called 64DD.      | published | no     |
      | NEC VR4300 CPU                        | Emulators  | 2018-10-03 4:27am | Update of the emulation library. | published | no     |
    And event content:
      | title               | collection | created           | field_paragraphs_body                       | state     | pinned |
      | 20 year anniversary | Nintendo64 | 2018-10-01 4:29am | The console was released in September 1996. | published | no     |
      | 30 year anniversary | Nintendo64 | 2017-10-01 4:29am | 30 year anniversary of Nintendo64.          | published | yes    |
    And discussion content:
      | title                           | collection | content                 | created          | state     | pinned |
      | What's your favourite N64 game? | Nintendo64 | Post title and reasons. | 2018-11-17 10:17 | published | no     |
      | Searching for green pad.        | Nintendo64 | Looking for a used one. | 2018-11-17 10:18 | published | no     |
    And the following collection user membership:
      | collection | user      | roles       |
      | Nintendo64 | jimmy7892 | facilitator |

  @javascript
  Scenario: Community content listing widget should be shown to facilitators and moderators.
    Given I am logged in as a facilitator of the "Nintendo64" collection
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    Then I should see the heading "Add custom page"
    And the following fields should not be present "Show related content, Allow shared content"
    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    Then the following fields should be present "Show related content, Allow shared content"
    And the following fields should not be present "Query presets, Limit"

    Given I am logged in as a moderator
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    Then I should see the heading "Add custom page"
    And the following fields should not be present "Show related content, Allow shared content"
    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    When I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And the following fields should be present "Show related content, Allow shared content, Query presets, Limit"
    And I should see the button "Add and configure filter"

  @javascript
  Scenario: Configure a custom page to show a community content listing.
    Given I am logged in as a facilitator of the "Nintendo64" collection
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    Then I should see the heading "Add custom page"
    When I fill in the following:
      | Title | Latest content |
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    When I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I select "News" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "News" with "Rare Nintendo64 disk drive discovered"
    And I select "Event" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Event" with "20 year anniversary"
    And I select "Custom page" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Custom page" with "Latest news listing"

    Then I should see the text "Related content from the community will be displayed below the one that you are publishing."
    And I should see the text "Display content shared from other communities."
    And I should see the text "Note: the content shown is dynamic, filtered live each time users will visualise the page. As a result, new content might be shown and old content can be altered or deleted."

    When I press "Save" in the "Modal buttons" region
    And I press "Publish"
    Then I should see the heading "Latest content"
    And I should see the "Rare Nintendo64 disk drive discovered" tile
    And I should see the "20 year anniversary" tile
    And I should see the "Latest news listing" tile
    # Content from other collections should not be shown.
    But I should not see the "NEC VR4300 CPU" tile

    # Change the page to list only news.
    Given I am logged in as a moderator
    And I go to the edit form of the "Latest content" "custom page"
    And I fill in the following:
      | Title | Latest news |
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Query presets" with "entity_bundle|news"
    When I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "Latest news"
    And I should see the "Rare Nintendo64 disk drive discovered" tile
    But I should not see the "20 year anniversary" tile
    And I should not see the "NEC VR4300 CPU" tile

    Given I am logged in as a facilitator of the "Nintendo64" collection
    And I go to the edit form of the "Latest news" "custom page"
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I check "Allow shared content"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    # Only news are displayed.
    Then I should see the "Rare Nintendo64 disk drive discovered" tile
    But I should not see the "20 year anniversary" tile
    And I should not see the "NEC VR4300 CPU" tile

    # Share a news inside the collection.
    When I go to the "NEC VR4300 CPU" news
    And I click "Share"
    And I check "Nintendo64"
    And I press "Share" in the "Modal buttons" region
    Then I should see the success message "Item was shared on the following groups: Nintendo64"

    When I go to the "Latest news" custom page
    Then I should see the "Rare Nintendo64 disk drive discovered" tile
    And I should not see the "NEC VR4300 CPU" tile
    But I should not see the "20 year anniversary" tile

    # The news is removed from the list as soon as it's removed from sharing.
    When I go to the homepage of the "Nintendo64" collection
    And I click the contextual link "Unshare" in the "NEC VR4300 CPU" tile
    And I check "Nintendo64"
    And I press "Submit" in the "Modal buttons" region
    Then I should see the success message "Item was unshared from the following groups: Nintendo64"

    When I go to the "Latest news" custom page
    Then I should see the "Rare Nintendo64 disk drive discovered" tile
    But I should not see the "20 year anniversary" tile
    And I should not see the "NEC VR4300 CPU" tile

    When I go to the edit form of the "Latest news" "custom page"
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I uncheck "Show related content"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should not see the "Rare Nintendo64 disk drive discovered" tile
    And I should not see the "20 year anniversary" tile
    And I should not see the "NEC VR4300 CPU" tile

  @javascript
  Scenario: Configure a custom page to show specific tiles.
    Given I am logged in as a facilitator of the "Nintendo64" collection
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    Then I should see the heading "Add custom page"
    When I fill in "Title" with "Chosen content"
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I uncheck "Show related content"
    When the following fields should not be visible "Global search, Allow shared content"
    And I check "Show related content"
    And I check "Global search"
    And the following fields should be visible "Global search, Allow shared content"
    And I select "Discussion" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Discussion" with "Searching for green pad."
    And I select "Discussion" from "Available filters"
    And I press "Add and configure filter"
    And I fill in the 2nd "Discussion" with "What's your favourite N64 game?" in the "Custom content listing" field
    And I select "News" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "News" with "Rare Nintendo64 disk drive discovered"
    And I select "Event" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Event" with "20 year anniversary"
    And I select "Collection" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Collection" with "Emulators"
    Then I drag the table row in the "Content listing field filter form" region at position 4 up
    And I press "Save" in the "Modal buttons" region
    And I press "Save as draft"
    Then I should see the heading "Chosen content"
    And I should see the following tiles in the correct order:
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | 20 year anniversary                   |
      | Rare Nintendo64 disk drive discovered |
      | Emulators                             |

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I drag the table row in the "Content listing field filter form" region at position 3 up
    And I drag the table row in the "Content listing field filter form" region at position 2 up
    And I click "Remove filter" at 5th filter of the 1st content listing paragraph
    And I uncheck "Global search"
    And I press "Save" in the "Modal buttons" region
    And I press "Save as draft"
    And I should see the following tiles in the correct order:
      | 20 year anniversary                   |
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | Rare Nintendo64 disk drive discovered |

    # Content that doesn't belong to the collection won't show up, even when selected.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I select "News" from "Available filters"
    And I press "Add and configure filter"
    And I fill in the 2nd "News" with "NEC VR4300 CPU" in the "Custom content listing" field
    And I check "Allow shared content"
    And I press "Save" in the "Modal buttons" region
    And I press "Save as draft"
    Then I should see the following tiles in the correct order:
      | 20 year anniversary                   |
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | Rare Nintendo64 disk drive discovered |

    # Content shared on the collection will be shown.
    When I go to the "NEC VR4300 CPU" news
    And I click "Share"
    And I check "Nintendo64"
    And I press "Share" in the "Modal buttons" region
    Then I should see the success message "Item was shared on the following groups: Nintendo64"
    When I go to the "Chosen content" custom page
    Then I should see the following tiles in the correct order:
      | 20 year anniversary                   |
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | Rare Nintendo64 disk drive discovered |
      | NEC VR4300 CPU                        |

    # Disabling inclusion of shared content will remove it from the list, even if still referenced.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I uncheck "Allow shared content"
    And I press "Save" in the "Modal buttons" region
    And I press "Save as draft"
    Then I should see the following tiles in the correct order:
      | 20 year anniversary                   |
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | Rare Nintendo64 disk drive discovered |

    # Create a solution and add it to the list.
    Given the following solution:
      | title      | N64 cartridge cleaner |
      | state      | published             |
      | collection | Nintendo64            |
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    # Simulate reordering and removal of rows.
    And I drag the table row in the "Content listing field filter form" region at position 4 up
    And I drag the table row in the "Content listing field filter form" region at position 3 up
    And I drag the table row in the "Content listing field filter form" region at position 1 down
    # Remove the first two rows.
    And I press "Remove filter"
    And I wait for AJAX to finish
    And I press "Remove filter"
    And I wait for AJAX to finish
    And I select "Solution" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Solution" with "N64 cartridge cleaner"
    And I press "Save" in the "Modal buttons" region
    And I press "Save as draft"
    Then I should see the following tiles in the correct order:
      | Searching for green pad.        |
      | What's your favourite N64 game? |
      | N64 cartridge cleaner           |

    # Query presets should still apply when available.
    When I am logged in as a moderator
    And I go to the "Chosen content" custom page
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I fill in "Query presets" with "entity_bundle|solution"
    And I press "Save" in the "Modal buttons" region
    And I press "Save as draft"
    Then I should see the following tiles in the correct order:
      | N64 cartridge cleaner |

    # When a query preset is entered, the query builder should not be available
    # anymore to facilitators.
    Given I am logged in as a facilitator of the "Nintendo64" collection
    And I go to the "Chosen content" custom page
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    Then the following field should not be present "Available filters"

    # Moderators should still have access to the query builder.
    When I am logged in as a moderator
    And I go to the "Chosen content" custom page
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    Then the following field should be present "Available filters"

  @javascript
  Scenario: Content type tabs should be mutually exclusive and show only items with results.
    Given I am logged in as a facilitator of the "Nintendo64" collection
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu

    Then I should see the heading "Add custom page"
    When I fill in the following:
      | Title | Collection content |
    And I select "HR" from "Topic"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I select "Discussion" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Discussion" with "Searching for green pad."
    And I select "Discussion" from "Available filters"
    And I press "Add and configure filter"
    And I fill in the 2nd "Discussion" with "What's your favourite N64 game?" in the "Custom content listing" field
    And I select "Event" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Event" with "20 year anniversary"
    And I select "News" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "News" with "Rare Nintendo64 disk drive discovered"
    And I press "Save" in the "Modal buttons" region
    And I press "Save"
    Then I should see the heading "Collection content"
    # Verify that unwanted facets are not shown in the page.
    And I should see the following facet items "Discussion, Event, News" in this order
    And I should see the following tiles in the correct order:
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | 20 year anniversary                   |
      | Rare Nintendo64 disk drive discovered |
    # Filter on news.
    When I click the News content tab
    Then I should see the "Rare Nintendo64 disk drive discovered" tile
    And I should not see the heading "20 year anniversary"
    # Some unwanted facets were showing after selecting one of the tabs.
    And I should see the following facet items "News, Discussion, Event" in this order
    # Filter on events.
    When I click the Event content tab
    Then I should see the heading "20 year anniversary"
    Then I should not see the heading "Rare Nintendo64 disk drive discovered"

  Scenario: Test listing by keywords that contain the same word.
    Given document content:
      | title        | keywords            | field_paragraphs_body | collection | state     |
      | User's Guide | nintendo64 manuals  | User manual           | Nintendo64 | published |
      | Licence      | nintendo64 licences | Licence               | Nintendo64 | published |
    And custom_page content:
      | title   | collection | state     |
      | Manuals | Nintendo64 | published |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "Manuals" title with:
    """
    a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:60:"entity_bundle|document
            keywords|"nintendo64 manuals"";s:5:"limit";s:2:"10";}
    """
    And custom_page content:
      | title    | collection | state     |
      | Licences | Nintendo64 | published |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "Licences" title with:
    """
    a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:61:"entity_bundle|document
            keywords|"nintendo64 licences"";s:5:"limit";s:2:"10";}
    """
    And I am logged in as a moderator

    When I go to the "Manuals" custom page
    Then I should see the "User's Guide" tile
    But I should not see the "Licence" tile

    When I go to the "Licences" custom page
    Then I should see the "Licence" tile
    But I should not see the "User's Guide" tile

  @javascript
  Scenario: Invalid entries in the query presets field show a validation error.
    Given I am logged in as a moderator
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    Then I should see the heading "Add custom page"
    When I fill in "Title" with "Query presets validation"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I fill in "Query presets" with "wrongvalue"
    And I press "Save" in the "Modal buttons" region
    Then I should see the error message "Invalid query preset line added: wrongvalue."
    When I fill in "Query presets" with "unknown_field|news"
    And I press "Save" in the "Modal buttons" region
    Then I should see the error message "Invalid search field specified: unknown_field."
    When I fill in "Query presets" with "entity_bundle|news|equal"
    And I press "Save" in the "Modal buttons" region
    Then I should see the error message "Invalid operator specified: equal. Allowed operators are '=', '<>', 'IN', 'NOT IN'."
    # Verify that errors are reported when multiline values are added.
    When I fill in "Query presets" with:
    """
    entity_bundle|news
    unknown_field|test
    """
    And I press "Save" in the "Modal buttons" region
    Then I should see the error message "Invalid search field specified: unknown_field."

  @javascript
  Scenario: Empty query presets and query builder field show a validation error.
    Given I am logged in as a moderator
    When I go to the homepage of the "Nintendo64" collection
    And I click "Add custom page" in the plus button menu
    Then I should see the heading "Add custom page"
    When I fill in "Title" with "Empty queries"
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    When I click "Choose component"
    And a modal should open
    And I click "Content listing"
    And I press "Save" in the "Modal buttons" region
    Then I should see the error message "You need to add a filter in the Content listing block"

  Scenario: Global search setting allows for site-wide content in the content listing.
    Given I am logged in as a moderator
    And custom_page content:
      | title       | collection | state     |
      | All content | Nintendo64 | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "All content" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:38:"entity_bundle\|discussion,event,news\|IN";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:1;} |

    When I go to the "All content" custom page
    # All tiles are available.
    And I should see the following tiles in the correct order:
      | 30 year anniversary                   |
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | NEC VR4300 CPU                        |
      | 20 year anniversary                   |
      | Rare Nintendo64 disk drive discovered |

    # Ensure that when content is created or editted in another group,
    # the page cache is invalidated.
    Given I go to the homepage of the "Emulators" collection
    And I click "Add news" in the plus button menu
    When I fill in the following:
      | Short title | Current wars               |
      | Abstract    | Lead text of current wars. |
      | Headline    | Edisson vs Electro         |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | A new movie is coming out. |
      | field_body:format | content_editor             |
    And I select "Statistics and Analysis" from "Topic"
    And I press "Publish"
    Then I should see the text "Current wars"

    When I go to the "All content" custom page
    And I should see the following tiles in the correct order:
      | 30 year anniversary                   |
      | Current wars                          |
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | NEC VR4300 CPU                        |
      | 20 year anniversary                   |
      | Rare Nintendo64 disk drive discovered |

    When I go to the edit form of the "Current wars" news
    And I fill in "Short title" with "Current wars is over"
    And I press "Update"
    Then I should see the text "Current wars is over"

    When I go to the "All content" custom page
    And I should see the following tiles in the correct order:
      | 30 year anniversary                   |
      | Current wars is over                  |
      | Searching for green pad.              |
      | What's your favourite N64 game?       |
      | NEC VR4300 CPU                        |
      | 20 year anniversary                   |
      | Rare Nintendo64 disk drive discovered |

  @javascript
  Scenario: Regression test for ISAICP-7040
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7040

    Given solutions:
      | title                | collection | state     |
      | Save the Planet      | Nintendo64 | published |
      | Fight Global Warming | Nintendo64 | published |
      | The Green Alliance   | Nintendo64 | published |

    And custom_page content:
      | title   | collection | state     |
      | Forests | Nintendo64 | published |

    Given I am logged in as jimmy7892
    And I go to the edit form of the "Forests" "custom page"

    # Paragraph #1.
    And I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    When I click "Choose component"
    And a modal should open
    And I click "Content listing"

    And I select "Solution" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Solution" with "Save the Planet"
    And I press "Save" in the "Modal buttons" region

    # Paragraph #2.
    When I click "Choose component"
    And a modal should open
    And I click "Content listing"

    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "Fight Global Warming"

    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "The Green Alliance"
    And I press "Save" in the "Modal buttons" region

    # Back to paragraph #1.
    And I "edit" from the "Custom page body" field the child 2 paragraph of 1 layout
    Then the 1st filter of the 1st content listing paragraph value is "Save the Planet"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the text "Forests"

  @javascript
  Scenario: Regression test for ISAICP-7183
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7183

    Given solutions:
      | title              | collection | state     |
      | Save gasoline      | Nintendo64 | published |
      | Fight battery mode | Nintendo64 | published |
      | Black Noise        | Nintendo64 | published |

    And custom_page content:
      | title | collection | state     |
      | Bikes | Nintendo64 | published |

    And I am logged in as jimmy7892
    And I go to the edit form of the "Bikes" "custom page"

    # Paragraph #1.
    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I click "Choose component"
    And a modal should open
    And I click "Content listing"

    And I select "Solution" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Solution" with "Save gasoline"

    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "Fight battery mode"

    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "Black Noise"
    And I press "Save" in the "Modal buttons" region

    # Paragraph #2.
    When I click "Choose component"
    And a modal should open
    And I click "Content listing"

    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "Black Noise"

    And I select "Solution" from latest "Available filters"
    And I press latest "Add and configure filter"
    And I fill in the latest "Solution" field with "Fight battery mode"
    And I press "Save" in the "Modal buttons" region

    Then I press "Update"
    And I should see the heading "Bikes"

    When I go to the edit form of the "Bikes" "custom page"
    And I "edit" from the "Custom page body" field the child 2 paragraph of 1 layout
    And I click "Remove filter" at 1st filter of the 1st content listing paragraph

    Then the 1st filter of the 1st content listing paragraph value is "Fight battery mode"
    And the 2nd filter of the 1st content listing paragraph value is "Black Noise"

    And I press "Add and configure filter"
    And the 3nd filter of the 1st content listing paragraph is empty
    And I click "Remove filter" at 1st filter of the 1st content listing paragraph
    And I press "Cancel" in the "Modal buttons" region

    When I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And the 1st filter of the 1st content listing paragraph value is "Black Noise"
    And the 2nd filter of the 1st content listing paragraph value is "Fight battery mode"

  Scenario: Regression test for ISAICP-7819
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7819

    Given event content:
      | title              | collection | state     | created           | start date | end date   |
      | Event pagination 1 | Nintendo64 | published | 2016-10-09 4:29am | now +8 day | now +9 day |
      | Event pagination 2 | Nintendo64 | published | 2016-10-08 4:29am | now +7 day | now +8 day |
      | Event pagination 3 | Nintendo64 | published | 2015-10-07 4:29am | now +6 day | now +7 day |
      | Event pagination 4 | Nintendo64 | published | 2014-10-06 4:29am | now +5 day | now +6 day |
      | Event pagination 5 | Nintendo64 | published | 2014-10-05 4:29am | now +4 day | now +5 day |
      | Event pagination 6 | Nintendo64 | published | 2013-10-04 4:29am | now +3 day | now +4 day |
      | Event pagination 7 | Nintendo64 | published | 2012-10-03 4:29am | now +2 day | now +3 day |
      | Event pagination 8 | Nintendo64 | published | 2011-10-02 4:29am | now +1 day | now +2 day |
      | Event pagination 9 | Nintendo64 | published | 2010-10-01 4:29am | now        | now +1 day |
    And news content:
      | title              | collection | state     |
      | News pagination 1  | Nintendo64 | published |
      | News pagination 2  | Nintendo64 | published |
      | News pagination 3  | Nintendo64 | published |
      | News pagination 4  | Nintendo64 | published |
      | News pagination 5  | Nintendo64 | published |
      | News pagination 6  | Nintendo64 | published |
      | News pagination 7  | Nintendo64 | published |
      | News pagination 8  | Nintendo64 | published |
      | News pagination 9  | Nintendo64 | published |
      | News pagination 10 | Nintendo64 | published |
      | News pagination 11 | Nintendo64 | published |
      | News pagination 12 | Nintendo64 | published |
      | News pagination 13 | Nintendo64 | published |
      | News pagination 14 | Nintendo64 | published |
      | News pagination 15 | Nintendo64 | published |
      | News pagination 16 | Nintendo64 | published |
      | News pagination 17 | Nintendo64 | published |
      | News pagination 18 | Nintendo64 | published |
      | News pagination 19 | Nintendo64 | published |
      | News pagination 20 | Nintendo64 | published |
      | News pagination 21 | Nintendo64 | published |
      | News pagination 22 | Nintendo64 | published |
      | News pagination 23 | Nintendo64 | published |
    And custom_page content:
      | title          | collection | state     |
      | Pagination bug | Nintendo64 | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Pagination bug" title:
      | content_listing:value | a:7:{s:6:"fields";a:2:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}s:22:"content_listing_status";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:1:"2";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Pagination bug" title:
      | content_listing:value | a:7:{s:6:"fields";a:2:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}s:22:"content_listing_status";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:19:"entity_bundle\|event";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:1:"2";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "Pagination bug" custom page
    And I click "Go to page 2"
    Then I should see the tiles in the correct order:
      | News pagination 3   |
      | News pagination 4   |
      | 30 year anniversary |
      | Event pagination 1  |

    When I click "Last page"
    Then I should see the tiles in the correct order:
      | News pagination 23                    |
      | Rare Nintendo64 disk drive discovered |
      | 30 year anniversary                   |
      | Event pagination 1                    |

    # Check second paragraph pagination.
    When I click "Go to page 2"
    Then I should see the tiles in the correct order:
      | News pagination 23                    |
      | Rare Nintendo64 disk drive discovered |
      | Event pagination 2                    |
      | Event pagination 3                    |

  @javascript
  Scenario: Facets are not shown when there are more than one paragraphs of the same datasource in the page.
    Given custom_page content:
      | title                  | collection | state     |
      | Multi paragraph facets | Nintendo64 | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Multi paragraph facets" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:38:"entity_bundle\|discussion,event,news\|IN";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:1;} |

    And I go to the "Multi paragraph facets" custom page
    Then I should see the heading "Multi paragraph facets"
    And I should see the following facet items "Discussion, Event, News" in this order

    Given I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Multi paragraph facets" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:38:"entity_bundle\|discussion,event,news\|IN";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:1;} |
    And I go to the "Multi paragraph facets" custom page
    Then I should see the heading "Multi paragraph facets"
    And I should not see the following facet items "Discussion, Event, News"
