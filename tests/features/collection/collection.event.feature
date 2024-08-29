@api @group-a
Feature: As a user of the website
  In order to find my way around events
  I need to be able to view collection events grouped properly.

  Scenario: Events should be filterable by future/past/my filters.
    Given users:
      | Username       | E-mail                      |
      | katerpillar    | kater.pillar@example.com    |
      | trustysidekick | trusty.sidekick@example.com |
    And collection content:
      | title      | state     |
      | Fairy Tail | published |
    And event content:
      | title                     | collection | start date   | end date            | created    | state     | author         | pinned |
      | Sweet Palm                | Fairy Tail | now -1 years | now -1 years +1 day | now -5 day | published | katerpillar    | yes    |
      | Melted Hairdresser        | Fairy Tail | now -2 day   | now +2 day          | now -4 day | published | katerpillar    | yes    |
      | Hot Air                   | Fairy Tail | now +3 day   | now +5 day          | now -3 day | published | katerpillar    | no     |
      | Walking Unofficial Humans | Fairy Tail | now -4 day   | now -2 day          | now        | published | trustysidekick | no     |
      # The "Spring Freezing" event is associated with the anonymous user.
      | Spring Freezing           | Fairy Tail | now +1 week  | now +1 week         | now -6 day | published |                | no     |
    And document content:
      | title       | collection | state     | created   |
      | Yellow Zeus | Fairy Tail | published | yesterday |

    When I am logged in as "katerpillar"
    And I go to the homepage of the "Fairy Tail" collection
    Then I should see the following tiles in the correct order:
      | Melted Hairdresser        |
      | Sweet Palm                |
      | Walking Unofficial Humans |
      | Yellow Zeus               |
      | Hot Air                   |
      | Spring Freezing           |
    And I should not see the link "Upcoming events (3)"
    When I click "Event"
    Then I should see the tiles in the correct order:
      | Melted Hairdresser        |
      | Sweet Palm                |
      | Spring Freezing           |
      | Hot Air                   |
      | Walking Unofficial Humans |
    And the "Collection event date" inline facet should allow selecting the following values:
      | My events (3)       |
      | Upcoming events (3) |
      | Past events (2)     |

    When I click "Upcoming events" in the "Collection event date" inline facet
    # The upcoming events, unlike the rest, are sorted in an 'ASC' order based on the field_event_date field value.
    Then I should see the following tiles in the correct order:
      | Melted Hairdresser |
      | Spring Freezing    |
      | Hot Air            |
    And the "Collection event date" inline facet should allow selecting the following values:
      | My events (3)   |
      | Past events (2) |
      | All events      |

    When I click "My events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      | Melted Hairdresser |
      | Sweet Palm         |
      | Hot Air            |
    And the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | All events          |

    When I click "Past events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      | Sweet Palm                |
      | Walking Unofficial Humans |
    And the "Collection event date" inline facet should allow selecting the following values:
      | My events (3)       |
      | Upcoming events (3) |
      | All events          |

    # The second level facet is deactivated together with its parent.
    When I click "Event"
    Then I should see the following tiles in the correct order:
      | Melted Hairdresser        |
      | Sweet Palm                |
      | Walking Unofficial Humans |
      | Yellow Zeus               |
      | Hot Air                   |
      | Spring Freezing           |

    When I am logged in as trustysidekick
    And I go to the homepage of the "Fairy Tail" collection
    And I click "Event"
    Then I should see the tiles in the correct order:
      | Melted Hairdresser        |
      | Sweet Palm                |
      | Spring Freezing           |
      | Hot Air                   |
      | Walking Unofficial Humans |
    And the "Collection event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | My events (1)       |

    # Tests facets with a different user to verify that cache leaks are prevented.
    When I click "Upcoming events" in the "Collection event date" inline facet
    Then I should see the following tiles in the correct order:
      | Melted Hairdresser |
      | Spring Freezing    |
      | Hot Air            |
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
      | Sweet Palm                |
      | Walking Unofficial Humans |
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
