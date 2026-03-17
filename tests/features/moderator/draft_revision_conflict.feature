@api @group-a
Feature: Saving a draft after content deletion

  Scenario: Saving a collection draft after deleting published news should not show a conflict error
    Given collection content:
      | title              |
      | Evergreen Archives |
    And news content:
      | title        | collection         |
      | Summer Recap | Evergreen Archives |

    When I am logged in as a moderator

    # Create a pending draft revision for the collection.
    When I go to the edit form of the "Evergreen Archives" collection
    And I press "Save as draft"
    Then I should see the success message "Collection Evergreen Archives has been updated."

    # Delete the published news.
    When I go to the edit form of the "Summer Recap" news
    And I click "Delete"
    And I press "Delete"

    # Try to save the collection draft again. This should not show a conflict error.
    When I go to the edit form of the "Evergreen Archives" collection
    And I press "Save as draft"
    Then I should not see the error message "The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved."
    And I should see the success message "Collection Evergreen Archives has been updated."

  Scenario: Saving a collection draft after deleting a published solution should not show a conflict error
    Given collection content:
      | title           |
      | Silver Archives |
    And solution content:
      | title       | collection      |
      | Blue Wrench | Silver Archives |

    When I am logged in as a moderator

    # Create a pending draft revision for the collection.
    When I go to the edit form of the "Silver Archives" collection
    And I press "Save as draft"
    Then I should see the success message "Collection Silver Archives has been updated."

    # Delete the published solution.
    When I go to the edit form of the "Blue Wrench" solution
    And I click "Delete"
    And I press "Delete"

    # Try to save the collection draft again. This should not show a conflict error.
    When I go to the edit form of the "Silver Archives" collection
    And I press "Save as draft"
    Then I should not see the error message "The content has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved."
    And I should see the success message "Collection Silver Archives has been updated."
