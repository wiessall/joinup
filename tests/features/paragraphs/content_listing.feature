@api @group-f
Feature:
  Functionality checks for content_listing paragraphs in custom pages.

  Background:
    Given collection:
      | title | Roman ruins |
      | state | published   |
      | topic | HR          |

  Scenario: Test that content listing options are working correctly.
    Given the following collections:
      | title  | state     |
      | Sparta | published |
    And news content:
      | title       | headline    | collection  | content       | state     |
      | Some news 1 | Some news 1 | Roman ruins | Some content. | published |
      | Some news 2 | Some news 2 | Sparta      | Come content. | published |

    And custom_page content:
      | title         | collection  | state     |
      | News carousel | Roman ruins | published |
    And I append "content_listing" to "field_paragraphs_body" field in "node" entity with following "News carousel" title:
      | content_listing:value | a:6:{s:6:"fields";a:1:{s:20:"content_listing_type";a:2:{s:6:"weight";i:0;s:6:"region";s:3:"top";}}s:7:"enabled";i:1;s:13:"query_presets";s:18:"entity_bundle\|news";s:5:"limit";s:2:"12";s:11:"show_shared";i:0;s:13:"global_search";i:0;} |

    When I go to the "News carousel" custom page

    # Ensure that listings are restricted only for group content by default.
    Then I should see the "Some news 1" tile
    But I should not see the "Some news 2" tile

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
