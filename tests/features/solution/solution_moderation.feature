@api @group-i
Feature: Solution moderation
  In order to manage solutions programmatically
  As a user of the website
  I need to be able to transit the solutions from one state to another.

  @javascript
  # Access checks are not being made here. They are run in the solution add feature.
  Scenario: 'Save as draft' and 'Propose' states are available but moderators should also see 'Publish' state.
    Given the following "collection" content:
      | title                         |
      | Collection propose state test |

    When I am logged in as a member of the "Collection propose state test" collection
    And I go to the add solution form of the "Collection propose state test" collection
    When I fill in "Title" with "Solution Beta"
    And I fill in "Abstract" with "Short abstract"
    And I enter "Last letter" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    Then I press "Create contact information"
    And I click "Additional information"
    And I select "Logging Service" options from "Solution type"
    And I press "Confirm"
    Then the following buttons should be present "Save as draft, Propose"
    And the following buttons should not be present "Publish, Request changes, Blacklist"
    And I should not see the link "Delete" in the "Content buttons"

    When I am logged in as a user with the "moderator" role
    And I go to the add solution form of the "Collection propose state test" collection
    When I fill in "Title" with "Solution Beta"
    And I fill in "Abstract" with "Short abstract"
    And I enter "Last letter" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    Then I press "Create contact information"
    And I click "Additional information"
    And I select "Logging Service" options from "Solution type"
    And I press "Confirm"
    Then the following buttons should be present "Save as draft, Propose, Publish"
    And the following buttons should not be present "Request changes, Blacklist"
    And I should not see the link "Delete" in the "Content buttons"

    When I am logged in as a "facilitator" of the "Collection propose state test" collection
    And I go to the add solution form of the "Collection propose state test" collection
    When I fill in "Title" with "Solution Beta"
    And I fill in "Abstract" with "Short abstract"
    And I enter "Last letter" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    Then I press "Create contact information"
    And I click "Additional information"
    And I select "Logging Service" options from "Solution type"
    And I press "Confirm"
    Then the following buttons should be present "Save as draft, Propose"
    And the following buttons should not be present "Publish, Request changes, Blacklist"
    And I should not see the link "Delete" in the "Content buttons"

  Scenario: Test the moderation workflow available states.
    Given users:
      | Username         | Roles     |
      # Authenticated user.
      | Isabel Banks     |           |
      # Moderator.
      | Tyrone Underwood | moderator |
      # Owner of all the solutions.
      | Franklin Walker  |           |
      # Facilitator of all the solutions.
      | William Curtis   |           |
    Given the following "solution" content:
      | title                      | description                | state                 |
      | Azure Ship                 | Azure ship                 | draft                 |
      | The Last Illusion          | The Last Illusion          | proposed              |
      | Rose of Doors              | Rose of Doors              | published             |
      | Time Bandit                | Time Bandit                | draft_after_published |
      | Fire Swords                | Fire Swords                | archived              |
      | The Guardian of the Stream | The Guardian of the Stream | needs update          |
      | Flames in the Swords       | Flames in the Swords       | blacklisted           |
    And the following solution user memberships:
      | solution                   | user            | roles       |
      | Azure Ship                 | Franklin Walker | owner       |
      | The Last Illusion          | Franklin Walker | owner       |
      | Rose of Doors              | Franklin Walker | owner       |
      | Time Bandit                | Franklin Walker | owner       |
      | Fire Swords                | Franklin Walker | owner       |
      | The Guardian of the Stream | Franklin Walker | owner       |
      | Flames in the Swords       | Franklin Walker | owner       |
      | Azure Ship                 | William Curtis  | facilitator |
      | The Last Illusion          | William Curtis  | facilitator |
      | Rose of Doors              | William Curtis  | facilitator |
      | Time Bandit                | William Curtis  | facilitator |
      | Fire Swords                | William Curtis  | facilitator |
      | The Guardian of the Stream | William Curtis  | facilitator |
      | Flames in the Swords       | William Curtis  | facilitator |

    # The following table tests the allowed transitions in a solution.
    # For each entry, the following steps must be performed:
    # Login with the given user (or a user with the same permissions).
    # Go to the homepage of the given solution.
    # If the expected states (states column) are empty, I should not have access
    # to the edit screen.
    # If the expected states are not empty, then I see the "Edit" link.
    # When I click the "Edit" link
    # Then the state field should have only the given states available.
    Then for the following solution, the corresponding user should have the corresponding available state buttons:
      | solution                   | user             | buttons                                                              |

      # The following solutions are tested as an owner. In reality, the owner is
      # also a facilitator so the only ability that differentiates the owner
      # from a facilitator is that they have the ability to delete their
      # solution when the solution is published. Note that the "Delete" option
      # is a link to a confirmation form which is styled to look as a button.
      # This is checked separately below.
      | Azure Ship                 | Franklin Walker  | Save as draft, Propose                                               |
      | The Last Illusion          | Franklin Walker  | Propose, Save as draft                                               |
      | Rose of Doors              | Franklin Walker  | Publish, Save as draft, Propose, Archive                             |
      | Time Bandit                | Franklin Walker  | Save as draft, Publish                                               |
      | Fire Swords                | Franklin Walker  |                                                                      |
      | The Guardian of the Stream | Franklin Walker  | Save as draft, Propose                                               |
      | Flames in the Swords       | Franklin Walker  | Save as draft, Propose                                               |

      # The following solutions do not follow the rule above and should be
      # tested as shown.
      | Azure Ship                 | William Curtis   | Save as draft, Propose                                               |
      | The Last Illusion          | William Curtis   | Propose, Save as draft                                               |
      | Rose of Doors              | William Curtis   | Publish, Save as draft, Propose, Archive                             |
      | Time Bandit                | William Curtis   | Save as draft, Publish                                               |
      | Fire Swords                | William Curtis   |                                                                      |
      | The Guardian of the Stream | William Curtis   | Save as draft, Propose                                               |
      | Flames in the Swords       | William Curtis   | Save as draft, Propose                                               |
      | Azure Ship                 | Isabel Banks     |                                                                      |
      | The Last Illusion          | Isabel Banks     |                                                                      |
      | Rose of Doors              | Isabel Banks     |                                                                      |
      | Time Bandit                | Isabel Banks     |                                                                      |
      | Fire Swords                | Isabel Banks     |                                                                      |
      | The Guardian of the Stream | Isabel Banks     |                                                                      |
      | Flames in the Swords       | Isabel Banks     |                                                                      |
      | Azure Ship                 | Tyrone Underwood | Save as draft, Propose, Publish                                      |
      | The Last Illusion          | Tyrone Underwood | Propose, Save as draft, Publish, Request changes                     |
      | Rose of Doors              | Tyrone Underwood | Publish, Save as draft, Propose, Request changes, Blacklist, Archive |
      | Time Bandit                | Tyrone Underwood | Save as draft, Publish                                               |
      | Fire Swords                | Tyrone Underwood | Unarchive                                                            |
      | The Guardian of the Stream | Tyrone Underwood | Save as draft, Propose, Publish                                      |
      | Flames in the Swords       | Tyrone Underwood | Save as draft, Propose, Publish                                      |

    # The 'Delete' action is not a button but a link leading to a confirmation
    # page that is styled as a button. It should only be available to the owner
    # and a moderator.
    And the visibility of the delete link should be as follows for these users in these solutions:
      | solution                   | user             | delete link |
      | Azure Ship                 | Franklin Walker  | no          |
      | The Last Illusion          | Franklin Walker  | no          |
      | Rose of Doors              | Franklin Walker  | yes         |
      | Fire Swords                | Franklin Walker  | no          |
      | The Guardian of the Stream | Franklin Walker  | no          |
      | Flames in the Swords       | Franklin Walker  | no          |
      | Azure Ship                 | William Curtis   | no          |
      | The Last Illusion          | William Curtis   | no          |
      | Rose of Doors              | William Curtis   | no          |
      | Fire Swords                | William Curtis   | no          |
      | The Guardian of the Stream | William Curtis   | no          |
      | Flames in the Swords       | William Curtis   | no          |
      | Azure Ship                 | Isabel Banks     | no          |
      | The Last Illusion          | Isabel Banks     | no          |
      | Rose of Doors              | Isabel Banks     | no          |
      | Fire Swords                | Isabel Banks     | no          |
      | The Guardian of the Stream | Isabel Banks     | no          |
      | Flames in the Swords       | Isabel Banks     | no          |
      | Azure Ship                 | Tyrone Underwood | yes         |
      | The Last Illusion          | Tyrone Underwood | yes         |
      | Rose of Doors              | Tyrone Underwood | yes         |
      | Fire Swords                | Tyrone Underwood | yes         |
      | The Guardian of the Stream | Tyrone Underwood | yes         |
      | Flames in the Swords       | Tyrone Underwood | yes         |

    # Access denied checks.
    Given I am logged in as "William Curtis"
    When I go to the edit form of the "Fire Swords" solution
    Then I should see the heading "Access denied"

    Given I am logged in as "Franklin Walker"
    When I go to the edit form of the "Fire Swords" solution
    Then I should see the heading "Access denied"

    Given I am logged in as "Isabel Banks"
    When I go to the edit form of the "Azure Ship" solution
    Then I should see the heading "Access denied"
    When I go to the edit form of the "The Last Illusion" solution
    Then I should see the heading "Access denied"
    When I go to the edit form of the "Rose of Doors" solution
    Then I should see the heading "Access denied"
    When I go to the edit form of the "Time Bandit" solution
    Then I should see the heading "Access denied"
    When I go to the edit form of the "Fire Swords" solution
    Then I should see the heading "Access denied"
    When I go to the edit form of the "The Guardian of the Stream" solution
    Then I should see the heading "Access denied"
    When I go to the edit form of the "Flames in the Swords" solution
    Then I should see the heading "Access denied"
