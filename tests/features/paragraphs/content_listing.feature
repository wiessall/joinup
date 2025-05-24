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
      | title       | headline    | collection  | content       | state     | abstract             | topic |
      | Some news 1 | Some news 1 | Roman ruins | Some content. | published | Some news 1 abstract | HR    |
      | Some news 2 | Some news 2 | Sparta      | Come content. | published | Some news 2 abstract | HR    |
      | Some news 3 | Some news 3 | Roman ruins | Come content. | published | Some news 3 abstract | info  |
    Given event content:
      | title        | headline     | collection  | content       | state     | start date | end date  | topic |
      | Some event 1 | Some event 1 | Roman ruins | Some content. | published | 5 day ago  | 4 day ago | HR    |
    And custom_page content:
      | title         | collection  | state     |
      | News carousel | Roman ruins | published |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "News carousel" title with:
    """
 a:7:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:-1;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:27:"entity_bundle|news
topic|hr";s:13:"show_archived";s:10:"unarchived";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;}
    """
    When I go to the "News carousel" custom page
    # Ensure that listings are restricted only for group content news of topic HR.
    Then I should see the "Some news 1" tile
    But I should not see the "Some news 2" tile
    And I should not see the "Some news 3" tile
    And I should not see the "Some event 1" tile

  Scenario: Content listing should be ordered by creation date by default.
    Given news content:
      | title       | headline    | collection  | content       | state     | created    |
      | Some news 1 | Some news 1 | Roman ruins | Some content. | published | 2 day ago  |
      | Some news 2 | Some news 2 | Roman ruins | Some content. | published | 3 days ago |
      | Some news 3 | Some news 3 | Roman ruins | Some content. | published | 1 days ago |
    And custom_page content:
      | title         | collection  | state     |
      | News carousel | Roman ruins | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "News carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "News carousel" custom page
    Then I should see the following tiles in the correct order:
      | Some news 3 |
      | Some news 1 |
      | Some news 2 |

  Scenario: Test also that events, when listed alone, are sorted by event date.
    Given event content:
      | title        | headline     | collection  | content       | state     | created    | start date | end date   |
      | Some event 1 | Some event 1 | Roman ruins | Some content. | published | 2 day ago  | 5 day ago  | 4 day ago  |
      | Some event 2 | Some event 2 | Roman ruins | Some content. | published | 3 days ago | 6 days ago | 5 days ago |
      | Some event 3 | Some event 3 | Roman ruins | Some content. | published | 1 days ago | 7 days ago | 6 days ago |
    And custom_page content:
      | title           | collection  | state     |
      | Events carousel | Roman ruins | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "Events carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:19:"entity_bundle\|event";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "Events carousel" custom page
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

    When I go to the "February events" custom page
    Then I should see the following tiles in the correct order:
      | Lumina Night Festival |
      | Stardust Gala Evening |

  Scenario: Content can be filtered by keywords.
    Given event content:
      | title                         | collection  | start date          | end date            | keywords |
      | Quantum Horizons Conference   | Roman ruins | 2025-01-12 12:00:00 | 2025-01-12 13:00:00 | drinks   |
      | Lumina Night Festival         | Roman ruins | 2025-02-15 12:00:00 | 2025-02-15 13:00:00 | food     |
      | Stardust Gala Evening         | Roman ruins | 2025-02-16 12:00:00 | 2025-02-16 13:00:00 | drinks   |
      | Global Tech Innovators Summit | Roman ruins | 2025-03-18 12:00:00 | 2025-03-18 13:00:00 | food     |
    And custom_page content:
      | title           | collection  |
      | February events | Roman ruins |
    And I append content listing to "field_paragraphs_body" field in "node" entity with following "February events" title with:
    """
    a:3:{s:7:"enabled";i:1;s:13:"query_presets";s:15:"keywords|drinks";s:5:"limit";s:2:"10";}
    """

    When I go to the "February events" custom page
    Then I should see the following tiles in the correct order:
      | Quantum Horizons Conference |
      | Stardust Gala Evening       |
