@api @group-g
Feature: Cloning of entities

  Background:
    Given collections:
      | title      | state     |
      | Collection | published |
    Given solution content:
      | title    | collection | state     |
      | Solution | Collection | published |
    And "custom_page" content:
      | title                  | collection | field_paragraphs_body          | state     |
      | Collection Custom Page | Collection | Collection custom page content | published |
      | Solution Custom Page   | Solution   | Solution custom page content   | published |

  Scenario Outline: Only moderators and facilitators can replicate a custom page.
    Given I am <role>
    And I go to the "<content>" custom page
    Then I <link availability> the link "Clone content"

    # Owners should also be facilitators, so we don't test them explicitly...
    # Yes, because testing with an only-admin user would fail.
    Examples:
      | role                                                    | content                | link availability |
      | an anonymous user                                       | Collection Custom Page | should not see    |
      | an anonymous user                                       | Solution Custom Page   | should not see    |
      | logged in as an authenticated                           | Collection Custom Page | should not see    |
      | logged in as an authenticated                           | Solution Custom Page   | should not see    |
      | logged in as a moderator                                | Collection Custom Page | should see        |
      | logged in as a moderator                                | Solution Custom Page   | should see        |
      | logged in as a member of the Collection collection      | Collection Custom Page | should not see    |
      | logged in as a member of the Collection collection      | Solution Custom Page   | should not see    |
      | logged in as a facilitator of the Collection collection | Collection Custom Page | should see        |
      | logged in as a facilitator of the Collection collection | Solution Custom Page   | should not see    |
      | logged in as a member of the Solution solution          | Collection Custom Page | should not see    |
      | logged in as a member of the Solution solution          | Solution Custom Page   | should not see    |
      | logged in as a facilitator of the Solution solution     | Collection Custom Page | should not see    |
      | logged in as a facilitator of the Solution solution     | Solution Custom Page   | should see        |

  Scenario Outline: Only custom page nodes should have the Replicate link
    Given <type> content:
      | title               | state     |
      | Test <type> content | published |
    When I am logged in as a moderator
    And I go to the "Test <type> content" <type>
    Then I <link availability> the link "Clone content" in the "Entity actions" region
    And I visit the replicate path of the "Test <type> content" content
    Then the response status code should be <status code>

    Examples:
      | type        | link availability | status code |
      | custom_page | should see        | 200         |
      | discussion  | should not see    | 404         |
      | document    | should not see    | 404         |
      | event       | should not see    | 404         |
      | licence     | should not see    | 404         |
      | news        | should not see    | 404         |

  @javascript
  Scenario Outline: User replicates content
    Given users:
      | Username               | Roles     |
      | Moderator              | moderator |
      | Collection Owner       |           |
      | Collection Facilitator |           |
      | Solution Owner         |           |
      | Solution Facilitator   |           |
    And the following collection user memberships:
      | collection | user                   | roles              |
      | Collection | Collection Owner       | owner, facilitator |
      | Collection | Collection Facilitator | facilitator        |
    And the following solution user memberships:
      | solution | user                 | roles              |
      | Solution | Solution Owner       | owner, facilitator |
      | Solution | Solution Facilitator | facilitator        |
    Given I am logged in as "<user>"
    And I go to the "<group label> Custom Page" custom page
    Then I should see the link "Clone content" in the "Entity actions" region
    Given I mark all emails as read
    And I open the header local tasks menu
    When I click "Clone content"
    Then I should see the heading "Set the title of the new content item cloned from <group label> Custom Page"
    When I fill in "New label (English)" with "This is the new title of the content"
    And I press "Clone content"
    Then I should see the heading "Edit Custom page This is the new title of the content"
    And the workflow state of the "This is the new title of the content" content is set to "draft"
    And I should see the text "<group label> custom page content" in the "Layout paragraphs widget" region
    And I should see the following success messages:
      | success messages                                                                                                |
      | The <group label> Custom Page custom page content item has been cloned as This is the new title of the content. |
    # Anonymous, authenticated users and group members are unable to access the new content.
    Given I am an anonymous user
    When I go to the "This is the new title of the content" custom page
    Then I should see the heading "Sign in to continue"
    Given I am logged in as an "authenticated user"
    When I go to the "This is the new title of the content" custom page
    Then I should see the heading "Access denied"
    Given I am logged in as a member of the Collection collection
    When I go to the "This is the new title of the content" custom page
    Then I should see the heading "Access denied"

    Examples:
      | user                   | group label |
      | Moderator              | Collection  |
      | Moderator              | Solution    |
      | Collection Facilitator | Collection  |
      | Solution Facilitator   | Solution    |
      | Collection Owner       | Collection  |
      | Solution Owner         | Solution    |
