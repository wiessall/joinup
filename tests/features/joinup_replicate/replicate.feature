@api @group_c
Feature: Cloning of entities

  Background:
    Given collections:
      | title            | state     |
      | Collection       | published |
    And solutions:
      | title          | collection       | state     |
      | Solution       | Collection       | published |
    And "custom_page" content:
      | title                  | collection | field_paragraphs_body          | state     |
      | Collection Custom Page | Collection | Collection custom page content | published |
      | Solution Custom Page   | Solution   | Solution custom page content   | published |

  Scenario Outline: Only moderators, owners and facilitators can replicate a custom page.
    Given I am <role>
    And I go to the "<content>" custom page
    Then I <link availability> the link "Replicate"

    Examples:
      | role                                                    | content                | link availability |
      | an anonymous user                                       | Collection Custom Page | should not see |
      | an anonymous user                                       | Solution Custom Page   | should not see |
      | logged in as an authenticated                           | Collection Custom Page | should not see |
      | logged in as an authenticated                           | Solution Custom Page   | should not see |
      | logged in as a moderator                                | Collection Custom Page | should see     |
      | logged in as a moderator                                | Solution Custom Page   | should see     |
      | logged in as a member of the Collection collection      | Collection Custom Page | should not see |
      | logged in as a member of the Collection collection      | Solution Custom Page   | should not see |
      | logged in as a facilitator of the Collection collection | Collection Custom Page | should see     |
      | logged in as a facilitator of the Collection collection | Solution Custom Page   | should not see |
      | logged in as a owner of the Collection collection       | Collection Custom Page | should see     |
      | logged in as a owner of the Collection collection       | Solution Custom Page   | should not see |
      | logged in as a member of the Solution solution          | Collection Custom Page | should not see |
      | logged in as a member of the Solution solution          | Solution Custom Page   | should not see |
      | logged in as a facilitator of the Solution solution     | Collection Custom Page | should not see |
      | logged in as a facilitator of the Solution solution     | Solution Custom Page   | should see     |
      | logged in as a owner of the Solution solution           | Collection Custom Page | should not see |
      | logged in as a owner of the Solution solution           | Solution Custom Page   | should see     |

  Scenario Outline: Only custom page nodes should have the Replicate link
    Given <type> content:
      | title        | state     |
      | Test content | published |
    When I am logged in as a moderator
    And I go to the "Test content" <type>
    Then I <link availability> the link "Replicate" in the "Entity actions" region

    Examples:
      | type        | link availability |
      | custom_page | should see        |
      | discussion  | should not see    |
      | document    | should not see    |
      | event       | should not see    |
      | licence     | should not see    |
      | news        | should not see    |

  @javascript
  Scenario Outline: User replicates content
    Given I am logged in as a <role>
    And I go to the "<group label> Custom Page" custom page
    Then I should see the link "Replicate" in the "Entity actions" region
    Given I mark all emails as read
    And I open the header local tasks menu
    When I click "Replicate"
    Then I should see the heading "Set the title of the new content item cloned from <group label> Custom Page"
    When I fill in "New label (English)" with "This is the new title of the content"
    And I press "Replicate"
    Then I should see the heading "Edit Custom page This is the new title of the content"
    And the workflow state of the "This is the new title of the content" content is set to "draft"
    And I should see the text "<group label> custom page content" in the "Layout paragraphs widget" region
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
      | role                                     | group label |
      # | moderator                                | Collection  |
      # | moderator                                | Solution    |
      # | facilitator of the Collection collection | Collection  |
      # | facilitator of the Solution solution     | Solution    |
      # | owner of the Collection collection       | Collection  |
      | owner of the Solution solution           | Solution    |
