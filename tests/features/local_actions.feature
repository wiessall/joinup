@api
Feature:
  As an owner of the website
  In order for users to easier navigate in the website
  The local actions should be helpful in entities.

  # Covers joinup_core_menu_local_tasks_alter().
  Scenario: The 'View' local action does not show in the canonical path.
    Given I am logged in as a developer

    Given collection:
      | title | Test collection |
      | state | published       |
    When I go to the "Test collection" collection
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Translate" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given solution:
      | title      | Test solution   |
      | collection | Test collection |
      | state      | published       |
    When I go to the "Test solution" solution
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Translate" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given release:
      | title         | Test release  |
      | is version of | Test solution |
      | state         | published     |
    When I go to the "Test release" release
    Then I should not see the link "View" in the "Entity actions" region
    And I should not see the link "Manage content"
    And I should not see the link "Moderate content"
    When I click "Translate" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given distribution content:
      | title             | parent       | state     |
      | Test distribution | Test release | published |
    When I go to the "Test distribution" distribution
    Then I should not see the link "View" in the "Entity actions" region
    And I should not see the link "Manage content"
    And I should not see the link "Moderate content"
    When I click "Metadata" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    # TODO: Re-enable this test in ISAICP-8556.
    # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8556
    # Given licence content:
    #   | title        |
    #   | Test licence |
    # When I go to the "Test licence" licence
    # Then I should not see the link "View" in the "Entity actions" region
    # When I click "Metadata" in the "Entity actions" region
    # Then I should see the link "View" in the "Entity actions" region

    Given discussion content:
      | title           | collection      | state     |
      | Test discussion | Test collection | published |
    When I go to the "Test discussion" discussion
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given document content:
      | title         | collection      | state     |
      | Test document | Test collection | published |
    When I go to the "Test document" document
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given event content:
      | title      | collection      | state     |
      | Test event | Test collection | published |
    When I go to the "Test event" event
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given news content:
      | title     | collection      | state     |
      | Test news | Test collection | published |
    When I go to the "Test news" news
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region
