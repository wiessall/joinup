@api @group-g
Feature: Asset release moderation
  In order to manage releases
  As a user of the website
  I need to be able to transit the releases from one state to another.

  Scenario: Publish, Update, request changes, publish again and delete a release.
    Given owner content:
      | name        | owner type |
      | Kenny Logan | Company    |
    And contact_information content:
      | name       | email                  |
      | SheriMoore | SheriMoore@example.com |
    And users:
      | Username        | E-mail                      | First name | Family name | Roles     |
      | Bonnie Holloway | bonnie.holloway@example.com | Bonnie     | Holloway    |           |
      | Felix Russell   | Felix.Russell@example.com   | Felix      | Russell     |           |
      | Wilson Mendoza  | Wilson.Mendoza@example.com  | Wilson     | Mendoza     | moderator |
      | Patric Sereira  | Patric.Sereira@example.com  | Patric     | Sereira     | moderator |
    And solution content:
      | title     | description | logo     | owner       | contact information | state     |
      | Dark Ship | Dark ship   | logo.png | Kenny Logan | SheriMoore          | published |
    And the following solution user membership:
      | solution  | user            | roles       |
      | Dark Ship | Bonnie Holloway | owner       |
      | Dark Ship | Felix Russell   | facilitator |
    When I am logged in as "Bonnie Holloway"
    And I go to the homepage of the "Dark Ship" solution
    And I click "Add release" in the plus button menu
    Then I should see the heading "Add Release"
    And I should see the workflow buttons "Save as draft, Publish"
    And I fill in the following:
      | Name            | Release of the dark ship |
      | Release version | 1                        |
      | Release notes   | We go live.              |
    And I press "Save as draft"
    Then I should see the text "Release of the dark ship 1" in the "Page title" region
    When I click "Edit" in the "Entity actions" region
    Then the current workflow state should be "Draft"
    And I should see the workflow buttons "Save as draft, Publish"
    When I fill in "Release version" with "v1"
    And I press "Publish"
    Then I should see the text "Release of the dark ship v1" in the "Page title" region
    And 0 e-mails should have been sent

    When I click "Edit" in the "Entity actions" region
    And I should see the workflow buttons "Update, Save as draft, Archive"
    When I fill in "Release notes" with "We go live soon."
    And I press "Update"
    Then I should see the text "Release of the dark ship v1" in the "Page title" region
    And the following email should have been sent:
      | recipient | Felix Russell                                                                   |
      | subject   | Interoperable Europe Portal: A release has been updated                                              |
      | body      | The release Release of the dark ship, v1 of the solution Dark Ship was updated. |
    And the following email should have been sent:
      | recipient | Wilson Mendoza                                                                  |
      | subject   | Interoperable Europe Portal: A release has been updated                                              |
      | body      | The release Release of the dark ship, v1 of the solution Dark Ship was updated. |
    And the following email should have been sent:
      | recipient | Patric Sereira                                                                  |
      | subject   | Interoperable Europe Portal: A release has been updated                                              |
      | body      | The release Release of the dark ship, v1 of the solution Dark Ship was updated. |

    # Archive and unarchive as moderator.
    Given I am logged in as a moderator

    # Ensure that the moderator has access to the 'Authored on' field when creating a release.
    When I go to the homepage of the "Dark Ship" solution
    And I click "Add release" in the plus button menu
    Then I should see the text "Authored on"

    And I go to the "Release of the dark ship" release
    And I click "Edit" in the "Entity actions" region
    Then the current workflow state should be "Published"
    And I should see the workflow buttons "Update, Save as draft, Request changes, Archive"
    # The "Delete" button is actually a link to the confirmation form, but it is
    # styled to look identical to a button.
    And I should see the link "Delete"
    And the following fields should be present "Motivation"
    # Ensure that owners has access to override the creation date.
    # @see ISAICP-4068
    And I should see the text "Authored on"
    And the following fields should not be present "Langcode, Translation"
    And I press "Archive"
    Then I should see the text "Release of the dark ship" in the "Page title" region
    And I should see the text "Archived" in the "Page title" region
    And I go to the edit form of the "Release of the dark ship" release
    And the current workflow state should be "Archived"
    And I should see the workflow buttons "Update, Unarchive"
    And I press "Unarchive"
    Then I should see the text "Release of the dark ship" in the "Page title" region
    But I should not see the text "Archived" in the "Page title" region
    And I go to the edit form of the "Release of the dark ship" release
    And the current workflow state should be "Published"

    # Request changes as a moderator.
    When I fill in "Name" with "Release"
    And I press "Request changes"
    # Motivation required.
    Then I should see the error message "This action requires you to fill in the motivation field"
    When I fill in "Motivation" with "I don't like it"
    And I fill in "Authored on" with the date "2014-08-30"
    And I fill in "Authored on" with the time "23:59:00"
    And I press "Request changes"
    # The published version does not change.
    Then I should see the text "Release of the dark ship v1" in the "Page title" region
    And the email sent to "Bonnie Holloway" with subject "Interoperable Europe Portal: Modification of a release of your solution has been requested" contains the following lines of text:
      | the Interoperable Europe Portal moderation team requires editing the release Release, v1 of the solution Dark Ship due to I don't like it. |
      | If you think this action is not clear or not due, please contact Portal Support at                                    |
    And the following email should have been sent:
      | recipient | Felix Russell                                                                                                         |
      | subject   | Interoperable Europe Portal: Modification of a release of your solution has been requested                                                 |
      | body      | the Interoperable Europe Portal moderation team requires editing the release Release, v1 of the solution Dark Ship due to I don't like it. |

    # We do not show the created time in the UI so another visit to the edit screen is required.
    When I go to the "Release of the dark ship" release
    And I click "Edit" in the "Entity actions" region
    Then the "Authored on" date field should contain the datetime "2014-08-30 23:59:00"

    # Implement changes as a facilitator.
    When I am logged in as "Bonnie Holloway"
    And I go to the "Release of the dark ship" release
    Then I should see the text "Release of the dark ship v1" in the "Page title" region
    When I click "Edit" in the "Entity actions" region
    Then I should see the heading "Edit Release Release"
    And the current workflow state should be "Needs update"
    And I should see the workflow buttons "Update"
    When I fill in "Name" with "Release fix"
    And I press "Update"
    # The updated version is still not published.
    Then I should see the text "Release of the dark ship v1" in the "Page title" region

    # Approve changes as a moderator.
    When I am logged in as a moderator
    And I go to the "Release of the dark ship" release
    And I click "Edit" in the "Entity actions" region
    And I should see the workflow buttons "Update, Publish"
    And I press "Publish"
    # The published is updated.
    Then I should see the text "Release fix v1" in the "Page title" region
    And the following email should have been sent:
      | recipient | Bonnie Holloway                                                                                    |
      | subject   | Interoperable Europe Portal: Your release was accepted                                                                  |
      | body      | Your proposed Release fix, v1 for the solution "Dark Ship" has been validated as per your request. |

    # Check archive permissions author and facilitator.
    When I am logged in as "Bonnie Holloway"
    And I go to the "Release fix" release
    When I click "Edit" in the "Entity actions" region
    And I press "Archive"
    Then I should see the text "Archived" in the "Page title" region

    When I am logged in as a moderator
    And I go to the "Release fix" release
    And I click "Edit" in the "Entity actions" region
    And I press "Unarchive"
    Then I should not see the text "Archived" in the "Page title" region

    When I am logged in as "Felix Russell"
    And I go to the "Release fix" release
    When I click "Edit" in the "Entity actions" region
    And I press "Archive"
    Then I should see the text "Archived" in the "Page title" region

    When I am logged in as a moderator
    And I go to the "Release fix" release
    # Delete a release as a moderator.
    When I click "Edit" in the "Entity actions" region
    And I click "Delete"
    And I press "Delete"
    And the following email should have been sent:
      | recipient | Bonnie Holloway                                            |
      | subject   | Interoperable Europe Portal: A release has been deleted                         |
      | body      | release Release fix, v1 of Dark Ship solution was deleted. |
    And the following email should have been sent:
      | recipient | Felix Russell                                              |
      | subject   | Interoperable Europe Portal: A release has been deleted                         |
      | body      | release Release fix, v1 of Dark Ship solution was deleted. |
    And the following email should have been sent:
      | recipient | Patric Sereira                                             |
      | subject   | Interoperable Europe Portal: A release has been deleted                         |
      | body      | release Release fix, v1 of Dark Ship solution was deleted. |
