@api
Feature: Content Overview

  Scenario: View content overview as an anonymous user
    Given the following users:
      | Username     | First name | Family name | E-mail               |
      | batbull      | Simba      | Hobson      | simba3000@hotmail.de |
      | welshbuzzard | Titus      | Nicotera    | nicotito@example.org |
      | hatchingegg  | Korinna    | Morin       | korimor@example.com  |
    And the following "collection" content:
      | title             | description        | moderation |
      | Rumble collection | Sample description | yes        |
    And the following "event" content:
      | title                | collection        | created           |
      | Seventh Windows      | Rumble collection | 2018-10-03 4:21am |
      | A Night at the Opera | Rumble collection | 2018-10-03 4:17am |
    And the following "news" content:
      | title            | collection        | state     | author       | created           |
      | The Playful Tale | Rumble collection | published | batbull      | 2018-10-03 4:26am |
      | Night of Shadow  | Rumble collection | proposed  | welshbuzzard | 2018-10-03 4:26am |
    And the following "document" content:
      | title             | collection        | created           |
      | History of Flight | Rumble collection | 2018-10-03 4:19am |
    And the following "discussion" content:
      | title            | collection        | author      | created           |
      | The Men's Female | Rumble collection | hatchingegg | 2018-10-03 4:18am |

    # Check that visiting as a moderator does not create cache for all users.
    When I am logged in as a user with the "moderator" role
    And I visit the advanced search page
    Then a tour should be available
    And the page should be cacheable
    And I should see the following facet items "Discussion, Document, News, Events" in this order
    And the "Events" content tab is displayed
    And I should not see the following facet items "Collection"
    And I should see the following tiles in the correct order:
      | The Playful Tale     |
      | Seventh Windows      |
      | History of Flight    |
      | The Men's Female     |
      | A Night at the Opera |
    Then I should see the "Seventh Windows" tile
    And I should see the "The Playful Tale" tile
    And I should see the "History of Flight" tile
    And I should see the "The Men's Female" tile
    And I should see the "A Night at the Opera" tile
    And I should not see the "Rumble collection" tile
    And I should not see the "Night of Shadow" tile

    # The tiles for discussion and news entities should not display the author
    # of the entity.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4454
    And I should not see the text "Simba Hobson" in the "The Playful Tale" tile
    And I should not see the text "Korinna Morin" in the "The Men's Female" tile

    When I click the "Document" content tab
    Then I should see the following facet items "Document, Discussion, News, Events" in this order
    And I should see the following tiles in the correct order:
      | History of Flight |

    # Check page for authenticated users.
    When I am logged in as a user with the "authenticated" role
    And I visit the advanced search page
    Then I should see the "Seventh Windows" tile
    And I should see the "The Playful Tale" tile
    And I should see the "History of Flight" tile
    And I should see the "The Men's Female" tile
    And I should see the "A Night at the Opera" tile
    But I should not see the "Rumble collection" tile
    And I should not see the "Night of Shadow" tile
    And the page should be cacheable

    # Check the page for anonymous users.
    When I am an anonymous user
    And I visit the advanced search page
    Then I should see the "Seventh Windows" tile
    And I should see the "The Playful Tale" tile
    And I should see the "History of Flight" tile
    And I should see the "The Men's Female" tile
    And I should see the "A Night at the Opera" tile
    But I should not see the "Rumble collection" tile
    And I should not see the "Night of Shadow" tile
    And the page should be cacheable

  Scenario: Users should be able to use additional filters on events.
    Given the following users:
      | Username        | E-mail                       |
      | claricemitchell | clarice.mitchell@example.com |
      | jeffreypayne    | jeffrey.payne@example.com    |
    And the following "collection" content:
      | title           |
      | Barbaric Avenue |
    And the following "event" content:
      | title                | collection      | start date   | end date            | created    | author          |
      | Bitter Finger        | Barbaric Avenue | now -1 years | now -1 years +1 day | now -4 day | claricemitchell |
      | Frozen Barbershop    | Barbaric Avenue | now -1 day   | now +1 day          | now -3 day | claricemitchell |
      | Frozen Breeze        | Barbaric Avenue | now +2 day   | now +4 day          | now -2 day | claricemitchell |
      | Flying Official Fish | Barbaric Avenue | now -3 day   | now -1 day          | now        | jeffreypayne    |
    # Technical: use a separate step to create an event associated to the anonymous user.
    And the following "event" content:
      | title          | collection      | start date  | end date    | created    |
      | Autumn Boiling | Barbaric Avenue | now +1 week | now +1 week | now -5 day |
    And the following "discussion" content:
      | title           | collection      | created   |
      | Purple Poseidon | Barbaric Avenue | yesterday |
    Then break
    When I am logged in as claricemitchell
    And I visit the advanced search page
   # The date second level facet appears only after filtering for events.
    And I should not see the link "Upcoming events (3)"

    When I click "Events"
    Then I should see the tiles in the correct order:
      | Flying Official Fish |
      | Frozen Breeze        |
      | Frozen Barbershop    |
      | Bitter Finger        |
      | Autumn Boiling       |

    When I click "Upcoming events" in the "Event date" inline facet
    Then I should see the following tiles in the correct order:
      | Frozen Barbershop |
      | Frozen Breeze     |
      | Autumn Boiling    |
    And the "Event date" inline facet should allow selecting the following values:
      | My events (3)   |
      | Past events (2) |
      | All events      |

    When I click "My events" in the "Event date" inline facet
    Then I should see the following tiles in the correct order:
      | Frozen Breeze     |
      | Frozen Barbershop |
      | Bitter Finger     |
    And the "Event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | All events          |

    When I click "Past events" in the "Event date" inline facet
    Then I should see the following tiles in the correct order:
      | Flying Official Fish |
      | Bitter Finger        |
    And the "Event date" inline facet should allow selecting the following values:
      | My events (3)       |
      | Upcoming events (3) |
      | All events          |

    # The second level facet is deactivated together with its parent.
    When I click "Event"
    Then I should see the following tiles in the correct order:
      | Flying Official Fish |
      | Purple Poseidon      |
      | Frozen Breeze        |
      | Frozen Barbershop    |
      | Bitter Finger        |
      | Autumn Boiling       |

    When I am logged in as jeffreypayne
    And I visit the advanced search page
    And I click "Event"
    Then I should see the tiles in the correct order:
      | Autumn Boiling       |
      | Frozen Breeze        |
      | Frozen Barbershop    |
      | Flying Official Fish |
      | Bitter Finger        |
    And the "Event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | My events (1)       |

    # Tests facets with a different user to verify that cache leaks are prevented.
    When I click "Upcoming events" in the "Event date" inline facet
    Then I should see the following tiles in the correct order:
      | Frozen Barbershop |
      | Frozen Breeze     |
      | Autumn Boiling    |
    And the "Event date" inline facet should allow selecting the following values:
      | Past events (2) |
      | My events (1)   |
      | All events      |

    When I click "My events" in the "Event date" inline facet
    Then I should see the following tiles in the correct order:
      | Flying Official Fish |
    And the "Event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |
      | All events          |

    When I click "Past events" in the "Event date" inline facet
    Then I should see the following tiles in the correct order:
      | Flying Official Fish |
      | Bitter Finger        |
    And the "Event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | My events (1)       |
      | All events          |

    When I am an anonymous user
    And I visit the advanced search page
    And I click "Event"
    Then the "Event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | Past events (2)     |

    When I click "Upcoming events" in the "Event date" inline facet
    Then the "Event date" inline facet should allow selecting the following values:
      | Past events (2) |
      | All events      |

    When I click "Past events" in the "Event date" inline facet
    Then the "Event date" inline facet should allow selecting the following values:
      | Upcoming events (3) |
      | All events          |
