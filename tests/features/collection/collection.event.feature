@api @group-a
Feature: As a user of the website
  In order to find my way around events
  I need to be able to view collection events grouped properly.

  # @covers \Drupal\joinup_search\EventSubscriber\SearchApiFieldListingOrder::queryAlter()
  # @covers \_joinup_search_search_api_has_condition()
  Scenario: Events should be filterable by future/past/my filters.
    Given users:
      | Username       | E-mail                      |
      | katerpillar    | kater.pillar@example.com    |
      | trustysidekick | trusty.sidekick@example.com |
    And collection content:
      | title      | state     |
      | Fairy Tail | published |
    And event content:
      | title                     | collection | start date   | end date            | created    | state     | author         |
      | Sweet Palm                | Fairy Tail | now -1 years | now -1 years +1 day | now -5 day | published | katerpillar    |
      | Melted Hairdresser        | Fairy Tail | now -2 day   | now +2 day          | now -4 day | published | katerpillar    |
      | Hot Air                   | Fairy Tail | now +3 day   | now +5 day          | now -3 day | published | katerpillar    |
      | Walking Unofficial Humans | Fairy Tail | now -4 day   | now -2 day          | now        | published | trustysidekick |
      # The "Spring Freezing" event is associated with the anonymous user.
      | Spring Freezing           | Fairy Tail | now +1 week  | now +1 week         | now -6 day | published |                |
    And document content:
      | title       | collection | state     | created   |
      | Yellow Zeus | Fairy Tail | published | yesterday |

    When I am logged in as "katerpillar"
    And I go to the homepage of the "Fairy Tail" collection
    # By default, tiles are ordered descending on created value.
    # @see \Drupal\joinup_search\EventSubscriber\SearchApiFieldListingOrder::queryAlter()
    Then I should see the following tiles in the correct order:
      # Created: now
      | Walking Unofficial Humans |
      # Created: yesterday
      | Yellow Zeus               |
      # Created: now -3 day
      | Hot Air                   |
      # Created: now -4 day
      | Melted Hairdresser        |
      # Created: now -5 day
      | Sweet Palm                |
      # Created: now -6 day
      | Spring Freezing           |
    And I should not see the link "Upcoming events (3)"
    When I click "Event"
    # By default, when only events are displayed, they are ordered by event date
    # on a descending order.
    Then I should see the tiles in the correct order:
      | Spring Freezing           |
      | Hot Air                   |
      | Melted Hairdresser        |
      | Walking Unofficial Humans |
      | Sweet Palm                |
    And the "Collection event date" inline facet should allow selecting the following values:
      | My events (3)       |
      | Upcoming events (3) |
      | Past events (2)     |

    When I click "Upcoming events" in the "Collection event date" inline facet
    # Upcoming and past events are ordered by event date on a ascending order.
    # Events are sorted by their event date but selected by the event end date.
    Then I should see the following tiles in the correct order:
      # Event date: now -2 day
      # Event end date: now +2 day
      | Melted Hairdresser |
      # Event date: now +3 day
      # Event end date: now +5 day
      | Hot Air            |
      # Event date: now -1 years
      # Event end date: now -1 years +1 day
      | Spring Freezing    |
    And the "Collection event date" inline facet should allow selecting the following values:
      | My events (3)   |
      | Past events (2) |
      | All events      |

    # My events do not have a specific handling, so they are displayed in
    # descending event date order.
    When I click "My events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      | Hot Air            |
      | Melted Hairdresser |
      | Sweet Palm         |
    And the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | All events          |

    # Past events are sorted descending by event date.
    When I click "Past events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      # Event date: now -4 day
      | Walking Unofficial Humans |
      # Event date: now -1 years
      | Sweet Palm                |
    And the "Collection event date" inline facet should allow selecting the following values:
      | My events (3)       |
      | Upcoming events (3) |
      | All events          |

    # The second level facet is deactivated together with its parent.
    When I click "Event"
    Then I should see the following tiles in the correct order:
      | Walking Unofficial Humans |
      | Yellow Zeus               |
      | Hot Air                   |
      | Melted Hairdresser        |
      | Sweet Palm                |
      | Spring Freezing           |

    When I am logged in as trustysidekick
    And I go to the homepage of the "Fairy Tail" collection
    And I click "Event"
    Then I should see the tiles in the correct order:
      | Spring Freezing           |
      | Hot Air                   |
      | Melted Hairdresser        |
      | Walking Unofficial Humans |
      | Sweet Palm                |
    And the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | My events (1)       |

    # Tests facets with a different user to verify that cache leaks are prevented.
    When I click "Upcoming events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      | Melted Hairdresser |
      | Hot Air            |
      | Spring Freezing    |
    And the "Collection event date" inline facet should allow selecting the following values:
      | Past events (2) |
      | My events (1)   |
      | All events      |

    When I click "My events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      | Walking Unofficial Humans |
    And the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | All events          |

    When I click "Past events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      | Walking Unofficial Humans |
      | Sweet Palm                |
    And the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | My events (1)       |
      | All events          |

    When I am an anonymous user
    And I go to the homepage of the "Fairy Tail" collection
    And I click "Event"
    Then the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |

    When I click "Upcoming events" in the "Collection event date" inline facet
    Then the "Collection event date" inline facet should allow selecting the following values:
      | Past events (2) |
      | All events      |

    When I click "Past events" in the "Collection event date" inline facet
    Then the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | All events          |
