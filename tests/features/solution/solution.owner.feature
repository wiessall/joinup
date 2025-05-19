@api @group-i
Feature:
  In order to better manage my group members
  As a group owner
  I need to be able to give extra permissions to authors.

  Scenario Outline: Authors can add content regardless of group settings.
    Given collection content:
      | title             |
      | Author collection |
    And solution content:
      | title           | group_moderation | content_creation   | collection        |
      | Author solution | <moderation>     | <content creation> | Author collection |

    When I am logged in as an "author" of the "Author solution" solution
    And I go to the "Author solution" solution
    Then I should see the following links:
      | Add news       |
      | Add discussion |
      | Add document   |
      | Add event      |

    Examples:
      | moderation | content creation         |
      | yes        | registered users         |
      | yes        | facilitators and authors |
      | no         | registered users         |
      | no         | facilitators and authors |

  # Fix this in https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8779
  @wip
  @uploadFiles:logo.png
  Scenario: Assign ownership during creation of solutions through UI
    Given owner content:
      | name      | owner type |
      | Leechidna | Company    |
    And users:
      | Username          |
      | Solution API user |
    And collection content:
      | title                    | logo     | group_moderation | content_creation         |
      | This is a klm collection | logo.png | no               | facilitators and authors |
    And the following collection user memberships:
      | user              | collection               | roles       |
      | Solution API user | This is a klm collection | facilitator |
    And I am logged in as "Solution API user"
    When I visit the "This is a klm collection" collection
    # And I click on element ".mdl-button__ripple-container"
    Then I should see the link "Add solution"
    And I click "Add solution"
    And I check "I have read and accept the legal notice and I commit to manage my solution on a regular basis."
    And I press "Yes"
    When I fill in the following:
      | Title          | Solution API example                         |
      | Description    | We do not care that much about descriptions. |
      | Abstract       | We do not care that much about abstract.     |
      | Name           | Gopheadow                                    |
      | E-mail address | solutionAPI@example.com                      |
    And I attach the file "logo.png" to "Logo"
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Leechidna"
    And I select "Flemish" from "Language"
    And I select "Statistics and Analysis" from "Topic"
    And I select "Logging Service" from "Solution type"
    And I press "Add owner"
    And I press "Save"
    Then I should see the heading "Solution API example"
    And the user "Solution API user" should be the owner of the "Solution API example" solution

    # Clean up the solution that was created manually.
    Then I delete the "Solution API example" solution
