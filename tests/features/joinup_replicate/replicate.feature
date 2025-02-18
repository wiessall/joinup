@api @group-g
Feature: Cloning of entities

  Background:
    Given collection content:
      | title      | published at     |
      | Collection | 2014-07-13T13:00 |
    Given solution content:
      | title    | collection | published at     |
      | Solution | Collection | 2015-07-13T13:00 |
    And "custom_page" content:
      | title                  | collection | field_paragraphs_body          | published at     |
      | Collection custom page | Collection | Collection custom page content | 2016-07-13T13:00 |
      | Solution custom page   | Solution   | Solution custom page content   | 2016-05-13T13:00 |
    And "event" content:
      | title            | collection | published at     |
      | Collection event | Collection | 2017-07-13T13:00 |
      | Solution event   | Solution   | 2018-07-13T13:00 |
    And "news" content:
      | title           | collection | published at     |
      | Collection news | Collection | 2016-01-13T13:00 |
      | Solution news   | Solution   | 2016-02-13T13:00 |
    And "document" content:
      | title               | collection | published at     |
      | Collection document | Collection | 2017-10-13T13:00 |
      | Solution document   | Solution   | 2017-09-13T13:00 |
    Given users:
      | Username               | Roles     |
      | Authenticated          |           |
      | Moderator              | moderator |
      | Collection Owner       |           |
      | Collection Member      |           |
      | Collection Facilitator |           |
      | Solution Member        |           |
      | Solution Owner         |           |
      | Solution Facilitator   |           |
    And the following collection user memberships:
      | collection | user                   | roles              |
      | Collection | Collection Member      |                    |
      | Collection | Collection Facilitator | facilitator        |
      | Collection | Collection Owner       | owner, facilitator |
    And the following solution user memberships:
      | solution | user                 | roles              |
      | Solution | Solution Member      |                    |
      | Solution | Solution Facilitator | facilitator        |
      | Solution | Solution Owner       | owner, facilitator |

  Scenario Outline: Only moderators and facilitators can clone content.
    Given I am <user>
    And I go to the "Collection custom page" custom page
    Then I <collection link availability> the link "Clone content"
    And I go to the "Solution custom page" custom page
    Then I <solution link availability> the link "Clone content"
    And I go to the "Collection event" event
    Then I <collection link availability> the link "Clone content"
    And I go to the "Solution event" event
    Then I <solution link availability> the link "Clone content"
    And I go to the "Collection news" news
    Then I <collection link availability> the link "Clone content"
    And I go to the "Solution news" news
    Then I <solution link availability> the link "Clone content"
    And I go to the "Collection document" document
    Then I <collection link availability> the link "Clone content"
    And I go to the "Solution document" document
    Then I <solution link availability> the link "Clone content"

    Examples:
      | user                                  | collection link availability | solution link availability |
      | an anonymous user                     | should not see               | should not see             |
      | logged in as "Authenticated"          | should not see               | should not see             |
      | logged in as "Moderator"              | should see                   | should see                 |
      | logged in as "Collection Member"      | should not see               | should not see             |
      | logged in as "Collection Facilitator" | should see                   | should not see             |
      | logged in as "Collection Owner"       | should see                   | should not see             |
      | logged in as "Solution Member"        | should not see               | should not see             |
      | logged in as "Solution Facilitator"   | should not see               | should see                 |
      | logged in as "Solution Owner"         | should not see               | should see                 |

  Scenario Outline: Only custom page, document, event and news nodes should have the "Clone content" link
    Given <type> content:
      | title               |
      | Test <type> content |
    When I am logged in as a moderator
    And I go to the "Test <type> content" <type>
    Then I <link availability> the link "Clone content" in the "Entity actions" region
    And I visit the replicate path of the "Test <type> content" content
    Then the response status code should be <status code>

    Examples:
      | type        | link availability | status code |
      | custom_page | should see        | 200         |
      | event       | should see        | 200         |
      | news        | should see        | 200         |
      | document    | should see        | 200         |
      | discussion  | should not see    | 404         |
      | licence     | should not see    | 404         |

  Scenario Outline: User clones event, news, document.
    Given I am logged in as "<user>"
    And I go to the "replicate" content page of the type <content type> with the title "<node title>"
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
      | user                   | node title             | content type |
      | Moderator              | Collection custom page | custom_page  |
      | Moderator              | Solution custom page   | custom_page  |
      | Collection Facilitator | Collection custom page | custom_page  |
      | Solution Facilitator   | Solution custom page   | custom_page  |
      | Collection Owner       | Collection custom page | custom_page  |
      | Solution Owner         | Solution custom page   | custom_page  |
      # Testing events, news, and documents for collections only, they behave the same in solutions.
      | Moderator              | Collection event       | event        |
      | Collection Facilitator | Collection event       | event        |
      | Collection Owner       | Collection event       | event        |
      | Moderator              | Collection news        | news         |
      | Collection Facilitator | Collection news        | news         |
      | Collection Owner       | Collection news        | news         |
      | Moderator              | Collection document    | document     |
      | Collection Facilitator | Collection document    | document     |
      | Collection Owner       | Collection document    | document     |
