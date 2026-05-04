@api @group-i
Feature:
  In order to better manage my group members
  As a group owner
  I need to be able to give extra permissions to authors.

  Scenario Outline: Authors can add content regardless of group settings.
    Given the following "collection" content:
      | title             |
      | Author collection |
    And the following "solution" content:
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

  @javascript
  Scenario: Assign ownership during creation of solutions through UI
    Given the following "owner" content:
      | name      | owner type |
      | Leechidna | Company    |
    And users:
      | Username          |
      | Solution API user |
    And the following "collection" content:
      | title                    | logo     | group_moderation | content_creation         |
      | This is a klm collection | logo.png | no               | facilitators and authors |
    And the following collection user memberships:
      | user              | collection               | roles       |
      | Solution API user | This is a klm collection | facilitator |
    And I am logged in as "Solution API user"
    When I visit the "This is a klm collection" collection
    And I click "Add solution" in the plus button menu
    And I fill in "Title" with "Solution API example"
    And I fill in "Abstract" with "We do not care that much about abstract."
    And I enter "We do not care that much about descriptions." in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Create owner.
    And I press "Add existing owner"
    And I fill in "Owner" with "Leechidna"
    And I press "Add owner"
    # Create contact information.
    And I press "Create contact information"
    And I fill in "contact[form][0][title][0][value]" with "Leechidna"
    And I fill in "contact[form][0][email][value]" with "angela_crespi@glacmon.basel-uni.ch"
    And I press "Create contact information"
    And I click "Additional information"
    And I attach the file "logo.png" to "Logo"
    And I fill in "Alternative text" with "Alternative logo text"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Save"
    Then I should see the heading "Solution API example"
    And the user "Solution API user" should be the owner of the "Solution API example" solution
