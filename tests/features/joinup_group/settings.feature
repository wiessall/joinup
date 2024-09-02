@api @group-f
Feature: Collections/Solutions settings
  In order to edit collections/solutions settings
  As a collection/solution owner or moderator
  Moderator need to be able to edit collections/solutions settings through the UI

  Background:
    Given owner content:
      | name       | owner type |
      | Jonny inc. | Company    |

  @uploadFiles:logo.png
  Scenario: Edit settings in a collection
    Given owner content:
      | name          | owner type |
      | Chico example | Company    |
    And contact_information content:
      | name            | email                       |
      | Chico editorial | chico.editorial@example.com |
    And collection content:
      | title             | logo     | abstract                                    | access url        | closed | creation date    | description                     | content creation         | moderation | topic             | owner         | contact information | state     |
      | Jillian Land Eels | logo.png | Read up on all about <strong>spice</strong> | http://spice.com/ | yes    | 28-01-1995 12:05 | The spice elegance personified. | facilitators and authors | yes        | Supplier exchange | Chico example | Chico editorial     | published |
    When I am logged in as a owner of the "Jillian Land Eels" collection
    And I go to the homepage of the "Jillian Land Eels" collection
    And I should not see the link "Settings" in the "Entity actions" region

    When I am logged in as a moderator
    And I go to the homepage of the "Jillian Land Eels" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    Then I should see the heading "Jillian Land Eels collection settings"
    And the "Highlight only the first term appearance" checkbox should be checked
    And the "Announcements enabled" checkbox should be checked

    Then I uncheck "Announcements enabled"
    And I press "Save"
    Then I should see the heading "Jillian Land Eels"

    When I click "Settings" in the "Entity actions" region
    Then the "Highlight only the first term appearance" checkbox should be checked
    And the "Announcements enabled" checkbox should be unchecked

    When I am logged in as a owner of the "Jillian Land Eels" collection
    And I go to the homepage of the "Jillian Land Eels" collection
    And I click "Add solution" in the plus button menu
    And I check "I have read and accept the legal notice and I commit to manage my solution on a regular basis."
    And I press "Yes"
    Then I should see the heading "Add Solution"

    When I fill in the following:
      | Title          | Solution Beta    |
      | Abstract       | Short abstract   |
      | Description    | Last letter      |
      | Name           | Jony Burton      |
      | E-mail address | jonb@example.com |
    And I attach the file "logo.png" to "Logo"
    And I select "Flemish" from "Language"
    And I select "EU and European Policies" from "Topic"
    And I select "Logging Service" from "Solution type"

    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Jonny inc."
    And I press "Add owner"
    And I press "Propose"
    Then I should see the heading "Solution Beta"
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    But I should not see the text "Announcements enabled"

    When I am logged in as a moderator
    And I go to the homepage of the "Solution Beta" solution
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    Then I should see the heading "Solution Beta solution settings"
    And the "Announcements enabled" checkbox should be checked

    # Cleanup.
    And I delete the "Solution Beta" solution

  Scenario: Testing group settings permissions
    Given collection content:
      | title          | logo     | state     |
      | Rally fanatics | logo.png | published |
    And solution content:
      | title        | state     | collection     |
      | Klop mirubes | published | Rally fanatics |
    And users:
      | Username         | E-mail                     | First name | Family name |
      | Ferners Lee      | ferners-lee@example.com    | Ferners    | Lee         |
      | Plot user        | plot.user@example.com      | Plot       | user        |
      | Lope Facilitator | lopfacilitator@example.com | Lope       | Facilitator |
    And the following collection user memberships:
      | collection     | user             | roles       |
      | Rally fanatics | Lope Facilitator | facilitator |
      | Rally fanatics | Plot user        | owner       |
    And the following solution user memberships:
      | solution     | user             | roles       |
      | Klop mirubes | Lope Facilitator | owner       |
      | Klop mirubes | Plot user        | facilitator |

    Given I am logged in as "Ferners Lee"
    When I go to the "Rally fanatics" collection
    And I should not see the link "Settings" in the "Entity actions" region
    Then I go to the homepage of the "Klop mirubes" solution
    And I should not see the link "Settings" in the "Entity actions" region

    Given I am logged in as "Plot user"
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    But I should not see the text "Announcements enabled"
    Then I go to the homepage of the "Klop mirubes" solution
    And I should not see the link "Settings" in the "Entity actions" region

    Given I am logged in as "Lope Facilitator"
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    But I should not see the text "Announcements enabled"

    Given I am logged in as a moderator
    When I go to the "Rally fanatics" collection
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    And the "Announcements enabled" checkbox should be checked
    Then I go to the homepage of the "Klop mirubes" solution
    And I should see the link "Settings" in the "Entity actions" region
    And I click "Settings" in the "Entity actions" region
    And the "Announcements enabled" checkbox should be checked
