@api
Feature:
  As an owner of the website
  In order for users to easier navigate in the website
  The local actions should be helpful in entities.

  # Covers joinup_core_menu_local_tasks_alter().
  Scenario: The 'View' local action does not show in the canonical path.
    Given I am logged in as a developer

    Given the following "collection" content:
      | title           |
      | Test collection |
    When I go to the "Test collection" collection
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Metadata" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given the following "solution" content:
      | title         | collection      |
      | Test solution | Test collection |
    When I go to the "Test solution" solution
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Metadata" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given the following "release" content:
      | title        | is version of |
      | Test release | Test solution |
    When I go to the "Test release" release
    Then I should not see the link "View" in the "Entity actions" region
    And I should not see the link "Manage content"
    And I should not see the link "Moderate content"
    When I click "Metadata" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given the following "distribution" content:
      | title             | release      |
      | Test distribution | Test release |
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

    Given the following "discussion" content:
      | title           | collection      |
      | Test discussion | Test collection |
    When I go to the "Test discussion" discussion
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given the following "document" content:
      | title         | collection      |
      | Test document | Test collection |
    When I go to the "Test document" document
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given the following "event" content:
      | title      | collection      |
      | Test event | Test collection |
    When I go to the "Test event" event
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region

    Given the following "news" content:
      | title     | collection      |
      | Test news | Test collection |
    When I go to the "Test news" news
    Then I should not see the link "View" in the "Entity actions" region
    When I click "Delete" in the "Entity actions" region
    Then I should see the link "View" in the "Entity actions" region
