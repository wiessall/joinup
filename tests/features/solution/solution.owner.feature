@api @group-g
Feature:
  In order to better manage my group members
  As a group owner
  I need to be able to give extra permissions to authors.

  Scenario Outline: Authors can add content regardless of group settings.
    Given collection:
      | title | Author collection |
      | state | published         |
    And solution:
      | title            | Author solution    |
      | moderation       | <moderation>       |
      | content creation | <content creation> |
      | state            | published          |
      | collection       | Author collection  |

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

  @terms @uploadFiles:logo.png
  Scenario: Assign ownership during creation of solutions through UI
    Given the following owner:
      | name      | type            |
      | Leechidna | Local Authority |
    And users:
      | Username          |
      | Solution API user |
    And the following collection:
      | title            | This is a klm collection |
      | logo             | logo.png                 |
      | moderation       | no                       |
      | closed           | no                       |
      | content creation | facilitators and authors |
      | state            | published                |
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
      | Name           | Gopheadow                                    |
      | E-mail address | solutionAPI@example.com                      |
    And I attach the file "logo.png" to "Logo"
    And I press "Add existing" at the "Owner" field
    # Then I wait for AJAX to finish
    And I fill in "Owner" with "Leechidna"
    And I fill in "Language" with "http://publications.europa.eu/resource/authority/language/VLS"
    And I select "EU and European Policies" from "Topic"
    And I select "Citizen" from "Solution type"
    And I press "Add owner"
    And I press "Save"
    Then I should see the heading "Solution API example"
    And the user "Solution API user" should be the owner of the "Solution API example" solution
