@api @group-e
Feature: Tests the group landing page customization.

  Scenario Outline:
  - As an anonymous, when I visit a group, I cannot set its landing page.
  - As a user, when I visit a group, I cannot set its landing page.
  - As a group member, when I visit a group, I cannot set its landing page.
  - As a group facilitator, when I visit other group, I cannot its landing page.
  # This may be uncommented in ISAICP-7223.
  # - As a group facilitator, when I visit my group, I can set a landing page.
  - As a moderator, when I visit a group, I can set a landing page.

    Given the following <group>s:
      | title               | state     | description                     |
      | <group> group       | validated | The <group> group description   |
      | other <group> group | validated | Other <group> group description |
    And "custom_page" content:
      | title         | <group>       | body                      |
      | Custom Page 1 | <group> group | The custom page 1 content |
      | Custom Page 2 | <group> group | The custom page 2 content |
      | Custom Page 3 | <group> group | The custom page 3 content |

    Given I am not logged in
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"

    Given I am logged in as an authenticated
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"

    Given I am logged in as a member of the "<group> group" <group>
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"

    Given I am logged in as a facilitator of the "other <group> group" <group>
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"

    # ToDo: Re-enable this test when facilitators are also allowed to set the
    # landing page of the group, in ISAICP-7223.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7223
    # Given I am logged in as a facilitator of the "<group> group" <group>
    # When I go to the homepage of the "<group> group" <group>
    # Then I should see the heading "<group> group"
    # And I should see "The <group> group description"
    # Then I should see the link "Settings"
    # When I click "Settings"
    # Then the following fields should be present "Landing page"
    # And the "Landing page" select should contain the following options:
    #   | - Standard landing page - |
    #   | Custom Page 1             |
    #   | Custom Page 2             |
    #   | Custom Page 3             |
    # When I select "Custom Page 2" from "Landing page"
    # And I press "Save"
    # Then I should see the heading "<group> group"
    # And I should see "The custom page 2 content"
    # But I should not see "The <group> group description"

    Given I am logged in as a moderator
    When I go to the homepage of the "<group> group" <group>
    Then I should see the heading "<group> group"
    Then I should see the link "Settings"
    When I click "Settings"
    Then the following fields should be present "Landing page"
    And the "Landing page" select should contain the following options:
      | - Standard landing page - |
      | Custom Page 1             |
      | Custom Page 2             |
      | Custom Page 3             |
    When I select "Custom Page 3" from "Landing page"
    And I press "Save"
    Then I should see the heading "<group> group"
    And I should see "The custom page 3 content"
    But I should not see "The <group> group description"
    And I should not see "The custom page 2 content"

    When I click "Settings"
    When I select "- Standard landing page -" from "Landing page"
    And I press "Save"
    Then I should see the heading "<group> group"
    And I should see "The <group> group description"
    And I should not see "The custom page 2 content"
    And I should not see "The custom page 3 content"

    Examples:
      | group      |
      | collection |
      | solution   |

  # This scenario might be removed in ISAICP-7223.
  # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7223
  Scenario: As a group facilitator I cannot set the landing page.
    Given the following collections:
      | title            | state     | description                      |
      | collection group | validated | The collection group description |
    And the following solutions:
      | title          | state     | description                    |
      | solution group | validated | The solution group description |

    Given I am logged in as a facilitator of the "collection group" collection
    When I go to the homepage of the "collection group" collection
    And I click "Settings"
    Then the following fields should not be present "Landing page"

    Given I am logged in as a facilitator of the "solution group" solution
    When I go to the homepage of the "solution group" solution
    Then I should not see the link "Settings"
