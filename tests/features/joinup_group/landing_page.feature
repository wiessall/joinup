@api @group-d
Feature: Tests the group landing page customization.

  Scenario Outline:
  """
  - As an anonymous, when I visit a group, I cannot set its landing page.
  - As a user, when I visit a group, I cannot set its landing page.
  - As a group member, when I visit a group, I cannot set its landing page.
  - As a group facilitator, when I visit other group, I cannot its landing page.
  - As a moderator, when I visit a group, I can set a landing page.
  """
  # This may be uncommented in ISAICP-7223.
  # - As a group facilitator, when I visit my group, I can set a landing page.

    Given the following <group>s:
      | title               | state     | description                         |
      | <group> group       | published | The <group> arbitrary description   |
      | other <group> group | published | Other <group> arbitrary description |
    And "custom_page" content:
      | title         | <group>       | body                      | state     |
      | Custom Page 1 | <group> group | The custom page 1 content | published |
      | Custom Page 2 | <group> group | The custom page 2 content | published |
      | Custom Page 3 | <group> group | The custom page 3 content | published |
    Given I am not logged in
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"
    And I should see the following navigation menu items in the specified order:
      | text          |
      | Overview      |
      | Custom Page 1 |
      | Custom Page 2 |
      | Custom Page 3 |

    Given I am logged in as an authenticated
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"

    Given I am logged in as a member of the "<group> group" <group>
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"

    Given I am logged in as a facilitator of the "other <group> group" <group>
    When I go to the homepage of the "<group> group" <group>
    Then I should not see the link "Settings"
    And I should not see the link "Edit landing page"

    # ToDo: Re-enable this test when facilitators are also allowed to set the
    # landing page of the group, in ISAICP-7223.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7223
    # Given I am logged in as a facilitator of the "<group> group" <group>
    # When I go to the homepage of the "<group> group" <group>
    # Then I should see the heading "<group> group"
    # And I should see "The <group> arbitrary description"
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
    # But I should not see "The <group> arbitrary description"

    Given I am logged in as a moderator
    When I go to the homepage of the "<group> group" <group>
    Then I should see the heading "<group> group"
    Then I should see the link "Settings"
    And I should not see the link "Edit landing page"
    When I click "Settings"
    Then the following fields should be present "Landing page, Overview label"
    And the "Landing page" select should contain the following options:
      | - Standard landing page - |
      | Custom Page 1             |
      | Custom Page 2             |
      | Custom Page 3             |
    When I select "Custom Page 3" from "Landing page"
    And I fill in "Overview label" with "Welcome"
    And I press "Save"
    Then I should see the heading "<group> group"
    And I should see the heading "Custom Page 3"
    And I should see "The custom page 3 content"
    But I should not see "The <group> arbitrary description"
    And I should not see "The custom page 2 content"
    And I should see the link "Edit landing page"
    And I should see the following navigation menu items in the specified order:
      | text          |
      | Welcome       |
      | Custom Page 1 |
      | Custom Page 2 |
      | Custom Page 3 |

    When I click "Settings"
    When I select "- Standard landing page -" from "Landing page"
    And I press "Save"
    Then I should see the heading "<group> group"
    And I should see "The <group> arbitrary description"
    And I should not see the heading "Custom Page 3"
    And I should not see "The custom page 2 content"
    And I should not see "The custom page 3 content"
    And I should not see the link "Edit landing page"

    Examples:
      | group      |
      | collection |
      | solution   |

  # This scenario might be removed in ISAICP-7223.
  # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7223
  Scenario: As a group facilitator I cannot set the landing page.
    Given the following collections:
      | title            | state     | description                          |
      | collection group | published | The collection arbitrary description |
    And the following solutions:
      | title          | state     | description                        |
      | solution group | published | The solution arbitrary description |

    Given I am logged in as a facilitator of the "collection group" collection
    When I go to the homepage of the "collection group" collection
    And I click "Settings"
    Then the following fields should not be present "Landing page"

    Given I am logged in as a facilitator of the "solution group" solution
    When I go to the homepage of the "solution group" solution
    Then I should not see the link "Settings"

  Scenario: Do not allow to unpublish or delete a custom page set as landing page
    Given collections:
      | title          | state     |
      | Rally fanatics | published |
    And "custom_page" content:
      | title         | group          | body                      | state     |
      | Custom Page 1 | Rally fanatics | The custom page 1 content | published |
      | Custom Page 2 | My own book    | The custom page 2 content | published |
    Given I am logged in as a moderator
    When I go to the homepage of the "Rally fanatics" collection
    And I click "Settings"
    And I select "Custom Page 1" from "Landing page"
    And I press "Save"
    And I go to the edit form of the "Custom Page 1" "custom page"
    And I click "Delete" in the "Entity actions" region
    Then I should see "You can not delete this landing page!"
    And I should see "This page acts as landing page for the collection. In order to delete it should be first unset as landing page."
    When I go to the "Custom Page 2" custom page
    When I click "Delete" in the "Entity actions" region
    Then I should see "Are you sure you want to delete the content item Custom Page 2?"
    When I go to the edit form of the "Custom Page 1" "custom page"
    Then I should not see the button "Unpublish"
    When I go to the homepage of the "Rally fanatics" collection
    And I click "Settings"
    And I select "- Standard landing page -" from "Landing page"
    And I press "Save"
    And I go to the "Custom Page 1" custom page
    And I click "Delete" in the "Entity actions" region
    Then I should not see "You can not delete this landing page!"
    And I should not see "This page acts as landing page for the collection. In order to delete it should be first unset as landing page."

  Scenario: Landing custom page redirects to group page.
    Given collections:
      | title         | state     |
      | United States | published |
    And "custom_page" content:
      | title    | group         | body                                                                                 | state     |
      | Michigan | United States | Michigan is a state in the Great Lakes region of the Upper Midwestern United States. | published |

    When I go to the "Michigan" custom page
    Then I should be on "/collection/united-states/michigan"

    Given I am logged in as a moderator
    And I go to the homepage of the "United States" collection
    And I click "Settings"
    And I select "Michigan" from "Landing page"
    And I press "Save"

    When I go to the "Michigan" custom page
    Then I should be on "/collection/united-states"
