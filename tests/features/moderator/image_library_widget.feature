@api @uploadFiles:logo.png @group-h
Feature: As a moderator I want to be able to maintain sets of images to be
  reused as logo images for collections, solutions, events and news.

  Scenario Outline: Moderator tasks.

    Given I am logged in as a moderator
    When I click "Library"
    And I click "Add media"
    Then I should see the heading "Add media item"

    When I click "<media type>"
    Then I should see the heading "Add <media type>"
    When I fill in "Name" with "Whatever@<media type>"
    And I attach the file "logo.png" to "Add a new file"
    And I press "Upload"
    And I press "Save"
    Then I should see the success message "<media type> Whatever@<media type> has been created."

    When I click "Library"
    And I click "Edit" in the "Whatever@<media type>" row
    Then I should see the heading "Edit <media type> Whatever@<media type>"
    When I fill in "Name" with "NewName@<media type>"
    And I press "Save"
    Then I should see the success message "<media type> NewName@<media type> has been updated."

    When I click "Delete" in the "NewName@<media type>" row
    Then I should see the heading "Are you sure you want to delete the media item NewName@<media type>?"
    When I press "Delete"
    Then I should see the heading "Media"
    But I should not see the link "NewName@<media type>"

    Examples:
      | media type        |
      | Collection logo   |
      | Solution logo     |
      | Event logo        |
      | News logo         |
