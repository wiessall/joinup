@api @group-g
Feature: Cloning of entities

  Background:
    Given the following "solution" content:
      | title    | collection     | published at     |
      | Solution | Portal support | 2015-07-13T13:00 |
    And "custom_page" content:
      | title                  | collection     | field_paragraphs_body          | published at     |
      | Collection custom page | Portal support | Collection custom page content | 2016-07-13T13:00 |
      | Solution custom page   | Solution       | Solution custom page content   | 2016-05-13T13:00 |
    And "event" content:
      | title            | collection     | published at     |
      | Collection event | Portal support | 2017-07-13T13:00 |
      | Solution event   | Solution       | 2018-07-13T13:00 |
    And "news" content:
      | title           | collection     | published at     |
      | Collection news | Portal support | 2016-01-13T13:00 |
      | Solution news   | Solution       | 2016-02-13T13:00 |
    And "document" content:
      | title               | collection     | published at     |
      | Collection document | Portal support | 2017-10-13T13:00 |
      | Solution document   | Solution       | 2017-09-13T13:00 |
    Given users:
      | Username         | Roles |
      | Collection Owner |       |
      | Solution Owner   |       |
    And the following collection user memberships:
      | collection     | user             | roles |
      | Portal support | Collection Owner | owner |
    And the following solution user memberships:
      | solution | user           | roles |
      | Solution | Solution Owner | owner |

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
      | user                                                       |
      | an anonymous user                                          |
      | logged in as an "authenticated user"                       |
      | logged in as a "member" of the "Portal support" collection |
      | logged in as a "member" of the "Solution" solution         |

  Scenario: Only custom page, document, event and news nodes should have the "Clone content" link
    Given "discussion" content:
      | title           | collection     |
      | Test discussion | Portal support |
    And "licence" content:
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
      | role                                                          | node title             | content type |
      | logged in as a moderator                                      | Collection custom page | custom_page  |
      | logged in as a moderator                                      | Solution custom page   | custom_page  |
      | logged in as a facilitator of the "Portal support" collection | Collection custom page | custom_page  |
      | logged in as a facilitator of the "Solution" solution         | Solution custom page   | custom_page  |
      | logged in as "Collection Owner"                               | Collection custom page | custom_page  |
      | logged in as "Solution Owner"                                 | Solution custom page   | custom_page  |
      # Testing events, news, and documents for collections only, they behave the same in solutions.
      | logged in as a moderator                                      | Collection event       | event        |
      | logged in as a facilitator of the "Portal support" collection | Collection event       | event        |
      | logged in as "Collection Owner"                               | Collection event       | event        |
      | logged in as a moderator                                      | Collection news        | news         |
      | logged in as a facilitator of the "Portal support" collection | Collection news        | news         |
      | logged in as "Collection Owner"                               | Collection news        | news         |
      | logged in as a moderator                                      | Collection document    | document     |
      | logged in as a facilitator of the "Portal support" collection | Collection document    | document     |
      | logged in as "Collection Owner"                               | Collection document    | document     |
