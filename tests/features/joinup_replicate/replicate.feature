@api @group-g
Feature: Cloning of entities

  Background:
    Given the following "collection" content:
      | title                | published at     |
      | Replicate collection | 2015-07-13T13:00 |
    And the following "solution" content:
      | title    | collection           | published at     |
      | Solution | Replicate collection | 2015-07-13T13:00 |
    And the following "custom_page" content:
      | title                  | collection           | field_paragraphs_body          | published at     |
      | Collection custom page | Replicate collection | Collection custom page content | 2016-07-13T13:00 |
      | Solution custom page   | Solution             | Solution custom page content   | 2016-05-13T13:00 |
    And the following "event" content:
      | title            | collection           | published at     |
      | Collection event | Replicate collection | 2017-07-13T13:00 |
      | Solution event   | Solution             | 2018-07-13T13:00 |
    And the following "news" content:
      | title           | collection           | published at     |
      | Collection news | Replicate collection | 2016-01-13T13:00 |
      | Solution news   | Solution             | 2016-02-13T13:00 |
    And the following "document" content:
      | title               | collection           | published at     |
      | Collection document | Replicate collection | 2017-10-13T13:00 |
      | Solution document   | Solution             | 2017-09-13T13:00 |
    Given the following users:
      | Username         | Roles |
      | Collection Owner |       |
      | Solution Owner   |       |
    And the following solution user memberships:
      | solution | user           | roles |
      | Solution | Solution Owner | owner |
    And the following collection user memberships:
      | collection           | user             | roles |
      | Replicate collection | Collection Owner | owner |

  Scenario Outline: Only moderators, owners and facilitators can clone content.
    Given I am <user>
    And I go to the "Collection custom page" custom_page
    Then I should not see the link "Clone content"
    And I go to the "Solution custom page" custom_page
    Then I should not see the link "Clone content"
    And I go to the "Collection event" event
    Then I should not see the link "Clone content"
    And I go to the "Solution event" event
    Then I should not see the link "Clone content"
    And I go to the "Collection news" news
    Then I should not see the link "Clone content"
    And I go to the "Solution news" news
    Then I should not see the link "Clone content"
    And I go to the "Collection document" document
    Then I should not see the link "Clone content"
    And I go to the "Solution document" document
    Then I should not see the link "Clone content"

    Examples:
      | user                                                             |
      | an anonymous user                                                |
      | logged in as an "authenticated user"                             |
      | logged in as a "member" of the "Replicate collection" collection |
      | logged in as a "member" of the "Solution" solution               |

  Scenario: Only custom page, document, event and news nodes should have the "Clone content" link
    Given the following "discussion" content:
      | title           | collection           |
      | Test discussion | Replicate collection |
    And the following "licence" content:
      | title        |
      | Test licence |
    When I am logged in as a moderator
    And I go to the "Test discussion" discussion
    Then I should not see the link "Clone content" in the "Entity actions" region
    And I go to the "Test licence" licence
    Then I should not see the link "Clone content" in the "Entity actions" region

  Scenario Outline: User clones event, news, document.
    Given I am <role>
    And I go to the "<node title>" <content type>
    Then I should see the link "Clone content"
    And I go to the replicate form of the "<node title>" <content type>
    Then I should see the heading "Set the title of the new content item cloned from <node title>"
    When I fill in "New label (English)" with "Cloned <node title>"
    And I press "Clone content"
    Then I should be on the "edit-form" page of the "Cloned <node title>" content
    And I should see the success message "content item has been cloned as Cloned <node title>."
    And the workflow state of the "Cloned <node title>" content should be "draft"
    And the "Cloned <node title>" "<content type>" should not be published
    # Check publication date.
    And the "Cloned <node title>" <content type> should not have a publication date

    Examples:
      | role                                                                | node title             | content type |
      | logged in as a moderator                                            | Collection custom page | custom_page  |
      | logged in as a moderator                                            | Solution custom page   | custom_page  |
      | logged in as a facilitator of the "Replicate collection" collection | Collection custom page | custom_page  |
      | logged in as a facilitator of the "Solution" solution               | Solution custom page   | custom_page  |
      | logged in as "Collection Owner"                                     | Collection custom page | custom_page  |
      | logged in as "Solution Owner"                                       | Solution custom page   | custom_page  |
      # Testing events, news, and documents for collections only, they behave the same in solutions.
      | logged in as a moderator                                            | Collection event       | event        |
      | logged in as a facilitator of the "Replicate collection" collection | Collection event       | event        |
      | logged in as "Collection Owner"                                     | Collection event       | event        |
      | logged in as a moderator                                            | Collection news        | news         |
      | logged in as a facilitator of the "Replicate collection" collection | Collection news        | news         |
      | logged in as "Collection Owner"                                     | Collection news        | news         |
      | logged in as a moderator                                            | Collection document    | document     |
      | logged in as a facilitator of the "Replicate collection" collection | Collection document    | document     |
      | logged in as "Collection Owner"                                     | Collection document    | document     |

  Scenario Outline: Cloning is not allowed when the parent group is archived.
    Given the workflow state of the "<group title>" <group type> is changed to "archived"
    When I am logged in as a moderator
    And I go to the replicate form of the "<node title>" <content type>
    Then I should get an access denied error
    When I am logged in as a facilitator of the "<group title>" <group type>
    And I go to the replicate form of the "<node title>" <content type>
    Then I should get an access denied error
    When I am logged in as "<owner>"
    And I go to the replicate form of the "<node title>" <content type>
    Then I should get an access denied error

    Examples:
      | group title          | group type | node title             | content type | owner            |
      | Replicate collection | collection | Collection custom page | custom_page  | Collection Owner |
      | Solution             | solution   | Solution custom page   | custom_page  | Solution Owner   |
