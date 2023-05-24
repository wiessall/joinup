@api
Feature:
  Access and functionality checks for carousel paragraphs in custom pages.

  Background:
    Given collection:
      | title | Roman ruins |
      | state | validated   |

  Scenario: Moderators can access paragraph.
    Given collection:
      | title | Ronup     |
      | state | validated |

    Given I am logged in as a moderator
    Then I am on the homepage
    And I go to the "Roman ruins" collection
    And I click "Add custom page"
    And I fill in "Title" with "Hello life"
    Then I should see the button "Add Carousel"

    When I press "Add Carousel"
    Then the following fields should be present "URL, Link text, Available filters"

    Given I am logged in as a facilitator of the "Ronup" collection
    And I go to the "Ronup" collection
    And I click "Add custom page"
    Then I should see the button "Add Carousel"

  Scenario: Test that carousels content listing options are working correctly.
    Given the following collections:
      | title       | state     |
      | Sparta      | validated |
    And news content:
      | title       | headline    | collection  | content       | state     |
      | Some news 1 | Some news 1 | Roman ruins | Some content. | validated |
      | Some news 2 | Some news 2 | Sparta      | Come content. | validated |

    When I am logged in as a moderator
    And I go to the homepage
    And I go to the "Roman ruins" collection
    And I click "Add custom page"

    When I fill in the following:
      | Title | News carousel        |
      | Body  | A carousel with news |
    And I press "Add Carousel"
    And I fill in "Query presets" with:
        """
        entity_bundle|news
        """
    When I press "Save"

    # Ensure that listings are restricted only for group content by default.
    Then I should see the "Some news 1" tile
    But I should not see the "Some news 2" tile

  Scenario: Test that carousels with specific content is working correctly.
    Given the following collections:
      | title       | state     |
      | Sparta      | validated |
    And custom_page content:
      | title       | headline    | collection  | content       | state     |
      | Some page 1 | Some page 1 | Roman ruins | Some content. | validated |
      | Some page 2 | Some page 2 | Sparta      | Some content. | validated |
      | Some page 3 | Some page 3 | Sparta      | Some content. | validated |

    When I am logged in as a moderator
    And I go to the homepage
    And I go to the "Roman ruins" collection
    And I click "Add custom page"

    When I fill in the following:
      | Title | Pages carousel               |
      | Body  | A carousel with custom pages |
    And I press "Add Carousel"

    And I select "Custom page" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Custom page" with "Some page 1"
    When I press "Save"

    Then I should see the "Some page 1" tile
    But I should not see the "Some page 2" tile
    And I should not see the "Some page 3" tile

    When I go to the edit form of the "Pages carousel" "custom page"
    And I fill in "Custom page" with "Some page 3"
    And I press "Save"

    # Global search is not checked. No tiles should be shown.
    Then I should not see the "Some page 1" tile
    And I should not see the "Some page 2" tile
    And I should not see the "Some page 3" tile

    When I go to the edit form of the "Pages carousel" "custom page"
    And I check "Global search"
    And I press "Save"
    Then I should see the "Some page 3" tile
    But I should not see the "Some page 1" tile
    And I should not see the "Some page 2" tile

  Scenario: Carousels should be ordered by creation date by default.
    Given news content:
      | title       | headline    | collection   | content       | state     | created    |
      | Some news 1 | Some news 1 | Roman ruins  | Some content. | validated | 2 day ago  |
      | Some news 2 | Some news 2 | Roman ruins  | Some content. | validated | 3 days ago |
      | Some news 3 | Some news 3 | Roman ruins  | Some content. | validated | 1 days ago |

    When I am logged in as a moderator
    And I go to the homepage
    And I go to the "Roman ruins" collection
    And I click "Add custom page"

    When I fill in the following:
      | Title | News carousel        |
      | Body  | A carousel with news |
    And I press "Add Carousel"
    And I fill in "Query presets" with:
        """
        entity_bundle|news
        """
    When I press "Save"
    Then I should see the following tiles in the correct order:
      | Some news 3 |
      | Some news 1 |
      | Some news 2 |

  Scenario: Test also that events, when listed alone, are sorted by event date.
    Given event content:
      | title        | headline     | collection   | content       | state     | created    | start date | end date |
      | Some event 1 | Some event 1 | Roman ruins  | Some content. | validated | 2 day ago  | 5 day ago  | 4 day ago |
      | Some event 2 | Some event 2 | Roman ruins  | Some content. | validated | 3 days ago | 6 days ago | 5 days ago |
      | Some event 3 | Some event 3 | Roman ruins  | Some content. | validated | 1 days ago | 7 days ago | 6 days ago |

    When I am logged in as a moderator
    And I go to the homepage
    And I go to the "Roman ruins" collection
    And I click "Add custom page"

    When I fill in the following:
      | Title | Events carousel        |
      | Body  | A carousel with events |
    And I press "Add Carousel"
    And I fill in "Query presets" with:
        """
        entity_bundle|event
        """
    When I press "Save"
    Then I should see the following tiles in the correct order:
      | Some event 1 |
      | Some event 2 |
      | Some event 3 |
