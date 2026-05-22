@api @group-f
Feature:
  As a moderator of the website
  I need to be able to see the unpublished content even for groups that don't
  have them listed in the canonical path.

  Scenario: Unpublished content are visible in the "Unpublished content" tab for IOPE.
    Given the following "collection" content:
      | uri                                                            | title                | state     |
      | http://data.europa.eu/w21/8e30f798-ff2b-478b-9c09-5ed5a63b4c8c | Interoperable Europe | published |
    And the following "news" content:
      | title      | collection           | state    |
      | IOP news 1 | Interoperable Europe | draft    |
      | IOP news 2 | Interoperable Europe | proposed |
    And the following "event" content:
      | title       | collection           | state    |
      | IOP event 1 | Interoperable Europe | draft    |
      | IOP event 2 | Interoperable Europe | proposed |
    And the following "document" content:
      | title          | collection           | state    |
      | IOP document 1 | Interoperable Europe | draft    |
      | IOP document 2 | Interoperable Europe | proposed |
    And the following "custom_page" content:
      | title             | collection           | state       |
      | IOP custom page 1 | Interoperable Europe | unpublished |

    When I am logged in as a moderator
    And I go to the "Interoperable Europe" collection
    Then I should see the link "Unpublished content"

    When I click "Unpublished content" in the "Entity actions" region
    Then I should see the heading "Unpublished content"
    And I should see the following tiles in the "Draft unpublished block" region:
      | IOP news 1     |
      | IOP event 1    |
      | IOP document 1 |
    And I should see the following tiles in the "Proposed unpublished block" region:
      | IOP news 2     |
      | IOP event 2    |
      | IOP document 2 |
    And I should see the following tiles in the "Other unpublished block" region:
      | IOP custom page 1 |

  Scenario: Unpublished content are visible in the "Unpublished content" tab for any group.
    Given the following "collection" content:
      | title      | state     |
      | Some group | published |
    And the following "news" content:
      | title             | collection | state    |
      | Some group news 1 | Some group | draft    |
      | Some group news 2 | Some group | proposed |
    And the following "event" content:
      | title              | collection | state    |
      | Some group event 1 | Some group | draft    |
      | Some group event 2 | Some group | proposed |
    And the following "document" content:
      | title                 | collection | state    |
      | Some group document 1 | Some group | draft    |
      | Some group document 2 | Some group | proposed |
    And the following "custom_page" content:
      | title                    | collection | state       |
      | Some group custom page 1 | Some group | unpublished |

    When I am logged in as a facilitator of the "Some group" collection
    And I go to the "Some group" collection
    Then I should see the link "Unpublished content"

    When I click "Unpublished content" in the "Entity actions" region
    Then I should see the heading "Unpublished content"
    And I should see the following tiles in the "Draft unpublished block" region:
      | Some group news 1     |
      | Some group event 1    |
      | Some group document 1 |
    And I should see the following tiles in the "Proposed unpublished block" region:
      | Some group news 2     |
      | Some group event 2    |
      | Some group document 2 |
    And I should see the following tiles in the "Other unpublished block" region:
      | Some group custom page 1 |
