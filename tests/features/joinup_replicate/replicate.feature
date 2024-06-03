@api @group_c
Feature: Cloning of entities

  Background:
    Given collections:
      | title            | state     |
      | Collection       | published |
      | Other Collection | published |
    And solutions:
      | title          | collection       | state     |
      | Solution       | Collection       | published |
      | Other Solution | Other Collection | published |
    And "custom_page" content:
      | title                     | collection | field_paragraphs_body             | state     |
      | Collection Custom Page    | Collection | Collection custom page content    | published |
      | Solution Custom Page      | Solution   | Solution custom page content      | published |

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
