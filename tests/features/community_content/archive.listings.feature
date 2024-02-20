@api @group-e
Feature: Archived content on content listings - groups and paragraphs.

  Background:
    Given owner:
      | name       |
      | Some owner |
    And contact:
      | name | Some contact |
    And collections:
      | title           | description | topic             | owner      | contact information | state     |
      | Some collection | Test        | Supplier exchange | Some owner | Some contact        | published |
      | Poly screens    | Test        | Supplier exchange | Some owner | Some contact        | published |
    And news content:
      | title          | headline       | state     | collection      | body | content | created         |
      | Archived news  | Archived news  | published | Some collection | Test | Test    | 2016-01-03T4:00 |
      | Published news | Published news | published | Some collection | Test | Test    | 2016-02-04T5:00 |
      | Poly news 1    | Poly news 1    | published | Poly screens    | Test | Test    | 2016-03-05T6:00 |
      | Poly news 2    | Poly news 2    | published | Poly screens    | Test | Test    | 2016-04-06T7:00 |
      | Poly news 3    | Poly news 3    | published | Poly screens    | Test | Test    | 2016-05-07T8:00 |
      | Poly news 4    | Poly news 4    | published | Poly screens    | Test | Test    | 2016-06-08T9:00 |
    # We need the "Archived news" content to be archived by altering its state
    # because "Unarchiving" it restored the previous revision and thus, needs
    # to have a previous revision (created as published).
    And the workflow state of the "Archived news" content is set to "archived"

  Scenario: Collection content listing can control archived content visibility.
    Given the following solutions:
      | title         | collection      | state     | creation date   |
      | Skewb cube    | Some collection | archived  | 2015-03-03T8:00 |
      | Pyraminx cube | Some collection | published | 2015-02-03T8:00 |

    When I am logged in as a moderator
    And I go to the edit form of the "Some collection" collection
    And I check "Enable the search field"
    And I press "Publish"
    Then I should see the heading "Some collection"
    # By default, 'Show archived content' is set to 'unarchived'.
    Then I should see the following tiles in the correct order:
      | Published news |
      | Pyraminx cube  |
    And I should not see the text "Archived" in the "Published news" tile
    And I should not see the text "Archived" in the "Pyraminx cube" tile

    When I go to the edit form of the "Some collection" collection
    And I select "Archived" from "Show content"
    And I press "Publish"
    Then I should see the heading "Some collection"
    Then I should see the following tiles in the correct order:
      | Archived news |
      | Skewb cube    |
    And I should see the text "Archived" in the "Archived news" tile
    And I should see the text "Archived" in the "Skewb cube" tile

    When I go to the edit form of the "Some collection" collection
    And I select "All" from "Show content"
    And I press "Publish"
    Then I should see the heading "Some collection"
    Then I should see the following tiles in the correct order:
      | Published news |
      | Pyraminx cube  |
    And I should not see the text "Archived" in the "Published news" tile
    And I should not see the text "Archived" in the "Pyraminx cube" tile

  @javascript
  Scenario: Content listing paragraph can control visibility of archived items.
    And custom_page content:
      | title     | collection      | state     |
      | Some page | Some collection | published |
      | Poly page | Some collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Some page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Poly page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "Some page" "custom page"
    And I select "HR" from "Topic"
    And I additionally select "Supplier exchange" from "Topic"
    And I click "Choose component"
    And I click "Content listing"
    And I fill in "Query presets" with "entity_bundle|news"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "Some page"
    # By default, 'Show content' is set to 'unarchived'.
    And I should see the "Published news" tile
    But I should not see the "Archived news" tile

    When I go to the edit form of the "Poly page" "custom page"
    And I select "Supplier exchange" from "Topic"
    And I click "Choose component"
    And I click "Content listing"
    And I select "All" from "Show content" in the "Modal content" region
    And I fill in "Query presets" with "entity_bundle|news"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"

    When I go to the "Poly page" custom page
    Then I should see the heading "Poly page"
    And I should see the text "Show archived"

    When I go to the "Some page" custom page
    Then I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I select "Archived" from "Show content" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "Some page"
    And I should see the "Archived news" tile
    But I should not see the "Published news" tile
    Then I should see the heading "Some page"
    And I should not see the text "Show archived"
    And I should see the "Archived news" tile
    And I should not see the "Published news" tile

    When I go to the "Some page" custom page
    Then I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I select "All" from "Show content" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "Some page"
    #Then I should see the following facet items "News, Show archived" in this order
    And I should see the text "Show archived"
    And I should not see the "Archived news" tile
    And I should see the "Published news" tile

    When I go to "/collection/some-collection/some-page?f[0]=status%3Aarchived"
    And I should see the "Archived news" tile
    And I should not see the "Published news" tile

    When I go to the "Some page" custom page
    Then I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I select "Unarchived" from "Show content" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "Some page"
    And I should not see the text "Show archived"
    And I should not see the "Archived news" tile
    And I should see the "Published news" tile

  @javascript
  Scenario: Carousel listings can also control archived items visibility.
    Given collection:
      | uri   | http://data.europa.eu/w21/8e30f798-ff2b-478b-9c09-5ed5a63b4c8c |
      | title | Interoperable Europe                                           |
      | state | published                                                      |
    And news content:
      | title              | headline           | state     | collection           | body | content |
      | IOP archived news  | IOP archived news  | archived  | Interoperable Europe | Test | Test    |
      | IOP published news | IOP published news | published | Interoperable Europe | Test | Test    |
    And custom_page content:
      | title    | collection           | state     |
      | IOP page | Interoperable Europe | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "IOP page" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    When I am logged in as a moderator
    And I go to the edit form of the "IOP page" "custom page"
    And I select "HR" from "Topic"
    And I click "Choose component"
    And I click "Carousel"
    And I fill in "Query presets" with "entity_bundle|news" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "IOP page"
    # By default, 'Show content' is set to 'unarchived'.
    And I should see the "IOP published news" tile
    But I should not see the "IOP archived news" tile

    When I go to the "IOP page" custom page
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I select "Archived" from "Show content" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the heading "IOP page"
    And I should see the "IOP archived news" tile
    But I should not see the "IOP published news" tile

    When I go to the "IOP page" custom page
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    And I select "All" from "Show content" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
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
      | Some discussion collection | Test        | Supplier exchange | Some owner | Some contact        | published |
    And discussion content:
      | title           | collection                 | state     |
      | Rand discussion | Some discussion collection | published |
      | Pest discussion | Some discussion collection | archived  |
      | Poll discussion | Some discussion collection | published |
      | Moon discussion | Some discussion collection | archived  |
      | Ruin discussion | Some discussion collection | published |

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
