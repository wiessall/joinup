@api @group-a
Feature: Freeze comments on archived content
  As a visitor of the website I can only view comment on archived community content.

  Background:
    Given the following "collection" content:
      | title             |
      | Gossip collection |
    And users:
      | Username        | E-mail                      | Roles | First name | Family name |
      | Sons of anarchy | sons-of-anarchy@example.com |       | Sons       | Anarchy     |

  Scenario Outline: Comment in 'View Only' state.
    Given <type> content:
      | title                              | body      | collection        | state    |
      | Test freeze of comments for <type> | body test | Gossip collection | archived |
    And comments:
      | subject                        | field_body       | author          | parent                             |
      | Comment with 'View Only' state | Let's all use it | Sons of anarchy | Test freeze of comments for <type> |

    When I am logged in as "Sons of anarchy"
    And I visit the "Test freeze of comments for <type>" "<type>"

    Then comment #1 should contain the markup "Let's all use it"
    And I should not see the button "Post comment" in the "Comment" region
    And I should not see the button "Delete" in the "Comment" region
    And I should not see the button "Edit" in the "Comment" region
    And I should not see the button "Reply" in the "Comment" region

    Examples:
      | type       |
      | news       |
      | event      |
      | discussion |
      | document   |
