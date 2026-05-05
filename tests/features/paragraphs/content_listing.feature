@api @group-h
Feature:
  Functionality checks for content_listing paragraphs in custom pages.

  Background:
    Given collection content:
      | title       | state     | topic |
      | Roman ruins | published | HR    |

  Scenario: Test that content listing options are working correctly.
    Given collection content:
      | title  | state     |
      | Sparta | published |
    And news content:
      | title       | headline    | collection  | body          | state     | abstract             | topic |
      | Some news 1 | Some news 1 | Roman ruins | Some content. | published | Some news 1 abstract | HR    |
      | Some news 2 | Some news 2 | Sparta      | Come content. | published | Some news 2 abstract | HR    |
      | Some news 3 | Some news 3 | Roman ruins | Come content. | published | Some news 3 abstract | info  |
    Given event content:
      | title        | headline     | collection  | body          | state     | start date | end date  | topic |
      | Some event 1 | Some event 1 | Roman ruins | Some content. | published | 5 day ago  | 4 day ago | HR    |
    And custom_page content:
      | title         | collection  | state     |
      | News carousel | Roman ruins | published |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "News carousel" title with:
    """
 a:7:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:27:"entity_bundle|news
topic|hr";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;}
    """
    When I go to the "News carousel" custom_page
    # Ensure that listings are restricted only for group content news of topic HR.
    Then I should see the "Some news 1" tile
    But I should not see the "Some news 2" tile
    And I should not see the "Some news 3" tile
    And I should not see the "Some event 1" tile

  Scenario: Content listing should be ordered by creation date by default.
    Given news content:
      | title       | headline    | collection  | body          | state     | created    |
      | Some news 1 | Some news 1 | Roman ruins | Some content. | published | 2 day ago  |
      | Some news 2 | Some news 2 | Roman ruins | Some content. | published | 3 days ago |
      | Some news 3 | Some news 3 | Roman ruins | Some content. | published | 1 days ago |
    And custom_page content:
      | title         | collection  | state     |
      | News carousel | Roman ruins | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "News carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "News carousel" custom_page
    Then I should see the following tiles in the correct order:
      | Some news 3 |
      | Some news 1 |
      | Some news 2 |

  Scenario: Test also that events, when listed alone, are sorted by event date.
    Given event content:
      | title        | headline     | collection  | body          | state     | created    | start date | end date   |
      | Some event 1 | Some event 1 | Roman ruins | Some content. | published | 2 day ago  | 5 day ago  | 4 day ago  |
      | Some event 2 | Some event 2 | Roman ruins | Some content. | published | 3 days ago | 6 days ago | 5 days ago |
      | Some event 3 | Some event 3 | Roman ruins | Some content. | published | 1 days ago | 7 days ago | 6 days ago |
    And custom_page content:
      | title           | collection  | state     |
      | Events carousel | Roman ruins | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Events carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:19:"entity_bundle\|event";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "Events carousel" custom_page
    Then I should see the following tiles in the correct order:
      | Some event 1 |
      | Some event 2 |
      | Some event 3 |

  Scenario: Events can be filtered by date.
    Given event content:
      | title                         | collection  | start date          | end date            |
      | Quantum Horizons Conference   | Roman ruins | 2025-01-12 12:00:00 | 2025-01-12 13:00:00 |
      | Lumina Night Festival         | Roman ruins | 2025-02-15 12:00:00 | 2025-02-15 13:00:00 |
      | Stardust Gala Evening         | Roman ruins | 2025-02-16 12:00:00 | 2025-02-16 13:00:00 |
      | Global Tech Innovators Summit | Roman ruins | 2025-03-18 12:00:00 | 2025-03-18 13:00:00 |
    And custom_page content:
      | title           | collection  |
      | February events | Roman ruins |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "February events" title with:
    """
    a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:59:"field_event_date|2025-01-31|>
    field_event_date|2025-03-01|<";s:5:"limit";s:2:"10";}
    """

    When I go to the "February events" custom_page
    Then I should see the following tiles in the correct order:
      | Lumina Night Festival |
      | Stardust Gala Evening |

  Scenario: Regression test - Content listing with corrupt query_builder filters does not crash on render.
    Given news content:
      | title          | headline       | collection  | body          | state     |
      | Corrupt news 1 | Corrupt news 1 | Roman ruins | Some content. | published |
    And custom_page content:
      | title              | collection  | state     |
      | Corrupt filter fix | Roman ruins | published |
    # Serialized value includes corrupt query_builder filters (null and missing plugin key).
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "Corrupt filter fix" title with:
    """
    a:8:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle|news";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;s:13:"query_builder";a:1:{s:7:"filters";a:2:{i:0;N;i:1;a:1:{s:5:"field";s:13:"entity_bundle";}}}}
    """
    When I go to the "Corrupt filter fix" custom_page
    Then I should see the heading "Corrupt filter fix"
    And the response status code should be 200

  @javascript
  Scenario: Regression test - Editing a content listing with corrupt query_builder filters does not crash the form.
    Given custom_page content:
      | title              | collection  | state     |
      | Corrupt edit check | Roman ruins | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Corrupt edit check" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "Corrupt edit check" title with:
    """
    a:8:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle|news";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;s:13:"query_builder";a:1:{s:7:"filters";a:2:{i:0;N;i:1;a:1:{s:5:"field";s:13:"entity_bundle";}}}}
    """
    And I am logged in as a facilitator of the "Roman ruins" collection
    And I go to the edit form of the "Corrupt edit check" "custom page"
    # Verify corrupt filters don't crash the edit form.
    And I "edit" from the "Custom page body" field the child 1 paragraph of 1 layout
    Then I should see "Show related content"
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    Then I should see the text "Corrupt edit check"

  Scenario: Content can be filtered by keywords.
    Given event content:
      | title                         | collection  | start date          | end date            | keywords |
      | Quantum Horizons Conference   | Roman ruins | 2025-01-12 12:00:00 | 2025-01-12 13:00:00 | games    |
      | Lumina Night Festival         | Roman ruins | 2025-02-15 12:00:00 | 2025-02-15 13:00:00 | Alphabet |
      | Stardust Gala Evening         | Roman ruins | 2025-02-16 12:00:00 | 2025-02-16 13:00:00 | games    |
      | Global Tech Innovators Summit | Roman ruins | 2025-03-18 12:00:00 | 2025-03-18 13:00:00 | Alphabet |
    And custom_page content:
      | title           | collection  |
      | February events | Roman ruins |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "February events" title with:
    """
    a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:14:"keywords|games";s:5:"limit";s:2:"10";}
    """

    When I go to the "February events" custom_page
    Then I should see the following tiles in the correct order:
      | Quantum Horizons Conference |
      | Stardust Gala Evening       |
