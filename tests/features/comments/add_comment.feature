@api @group-a
Feature: Add comments
  As a visitor of the website I can leave a comment on community content.

  Background:
    Given collection content:
      | title             | closed |
      | Gossip collection | no     |
      | Show collection   | yes    |
    And solution content:
      | title                | collection        |
      | Gossip girl solution | Gossip collection |
    And users:
      | Username           | E-mail                    | Roles | First name | Family name |
      | Layonel Sarok      | layonel.sarok@example.com |       | Layonel    | Sarok       |
      | Korma Salya        | korma.salya@example.com   |       | Korma      | Salya       |
      | Salma Hayek Coster | salma.coster@example.com  |       | Salma      | Coster      |
    And the following collection user memberships:
      | collection        | user          | roles                      |
      | Gossip collection | Layonel Sarok | administrator, facilitator |
      | Gossip collection | Korma Salya   | facilitator                |
    And the following solution user memberships:
      | solution             | user          | roles                      |
      | Gossip girl solution | Layonel Sarok | administrator, facilitator |
      | Gossip girl solution | Korma Salya   | facilitator                |

  # This scenario uses javascript to work as regression test for a bug that
  # makes CKEditor unusable upon a page load.
  # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3612
  @javascript
  Scenario Outline: Make an authenticated comment, skips moderation.
    And <content type> content:
      | title   | body                                                | <parent>       | author        |
      | <title> | How could this ever happen? Moral panic on its way! | <parent title> | Layonel Sarok |
    Given I am logged in as "Salma Hayek Coster"
    When I visit the "<title>" "<content type>"
    # Authenticated users can use a rich text editor to enter comments.
    And I should see the "Create comment" wysiwyg editor
    And the "Upload image from computer" button should be available in the "Create comment" wysiwyg editor
    When I enter "Mr scandal was doing something weird the other day." in the "Create comment" wysiwyg editor
    And I press "Post comment"
    Then I should not see the following success messages:
      | success messages                                                                                     |
      | Your comment has been queued for review by site administrators and will be published after approval. |
    And the page should contain the html text "Mr scandal was doing something weird the other day."
    # The author's full name should be shown, not the username.
    And I should see the link "Salma Coster"
    But I should not see the link "Salma Hayek Coster"
    And the email sent to "Layonel Sarok" with subject "Interoperable Europe Portal: A new comment has been created." contains the following lines of text:
      | Salma Coster posted a comment in <parent> "<parent title>".                             |
      | To view the comment click                                                               |
      | If you think this action is not clear or not due, please contact Portal Support at http |
    And the email sent to "Korma Salya" with subject "Interoperable Europe Portal: A new comment has been created." contains the following lines of text:
      | Salma Coster posted a comment in <parent> "<parent title>".                             |
      | To view the comment click                                                               |
      | If you think this action is not clear or not due, please contact Portal Support at http |

    # Verify the anchored link works properly.
    When I am logged in as "Layonel Sarok"
    And I click the comment link from the last email sent to "Layonel Sarok"
    Then I should see the heading "<title>"
    And I should see the text "Mr scandal was doing something weird the other day."

    When I am logged in as "Korma Salya"
    And I click the comment link from the last email sent to "Korma Salya"
    And I click "Reply"
    And I should see the "Create comment" wysiwyg editor
    When I enter "I am replying in user's comment." in the "Create comment" wysiwyg editor
    And I press "Post comment"
    # Check status message to ensure the POST request completed and emails were sent.
    And I should see the success message "Your comment has been posted"
    # Parent comment owner receives an email.
    And the email sent to "Layonel Sarok" with subject "Interoperable Europe Portal: A new comment has been created." contains the following lines of text:
      | Korma Salya posted a comment in <parent> "<parent title>".                              |
      | To view the comment click                                                               |
      | If you think this action is not clear or not due, please contact Portal Support at http |
    # The owner of the content does not receive a notification due to being the actor.
    And the following email should not have been sent:
      | recipient | Salma Coster                                                 |
      | subject   | Interoperable Europe Portal: A new comment has been created. |
      | body      | Salma Coster posted a comment in                             |

    Examples:
      | content type | title           | parent     | parent title         |
      | news         | Scandalous news | collection | Gossip collection    |
      | discussion   | Is gossip bad?  | collection | Gossip collection    |
      | news         | Scandalous news | solution   | Gossip girl solution |
      | document     | Wikileaks       | collection | Gossip collection    |
      | event        | Wikileaks       | collection | Gossip collection    |

  Scenario Outline: Posting comments.
    Given <content type> content:
      | title   | body                                                | collection        |
      | <title> | How could this ever happen? Moral panic on its way! | Gossip collection |
    Given I am logged in as "Salma Hayek Coster"
    When I visit the "<title>" "<content type>"
    # Authenticated users can insert <p> and <br> tags in the comment body.
    And I fill in "Create comment" with "<p>Mr scandal was doing something<br />weird the other day.<p/>"
    Then I press "Post comment"
    Then I should not see the following success messages:
      | success messages                                                                                     |
      | Your comment has been queued for review by site administrators and will be published after approval. |
    And comment #1 should contain the markup "<p>Mr scandal was doing something<br>weird the other day.</p>"
    And comment #1 indent is 0

    When I click "Reply" in comment #1
    And I fill in "Create comment" with "Comment indent 1"
    When I press "Post comment"
    Then comment #2 should contain the markup "Comment indent 1"
    And comment #2 indent is 1

    When I click "Reply" in comment #2
    And I fill in "Create comment" with "Comment indent 2"
    When I press "Post comment"
    # This comment hit the maximum deep.
    Then comment #3 should contain the markup "Comment indent 2"
    And comment #3 indent is 2

    # Reply to the deepest comment.
    When I click "Reply" in comment #3
    And I fill in "Create comment" with "Reply to #3"
    When I press "Post comment"
    Then comment #4 should contain the markup "Reply to #3"
    # The reply has the same indent as the parent comment.
    And comment #4 indent is 2

    # Reply to reply.
    When I click "Reply" in comment #4
    And I fill in "Create comment" with "Reply to reply"
    When I press "Post comment"
    Then comment #5 should contain the markup "Reply to reply"
    # The reply to reply has the same indent as the deepest comment.
    And comment #5 indent is 2

    Given I am an anonymous user
    When I visit the "<title>" "<content type>"
    # For anonymous users we display this message at the bottom of comment list.
    Then I should see the text "Login or create an account to comment."
    # But we don't show the Drupal core login/register links on each comment.
    But I should not see the text "Log in or register to post comments"

    Examples:
      | content type | title             |
      | news         | Scandalous news   |
      | discussion   | Is gossip bad?    |
      | document     | Wikileaks         |
      | event        | Quantum Echo Fest |

  Scenario Outline: Anonymized comments
    Given <content type> content:
      | title   | body                                                | collection   |
      | <title> | How could this ever happen? Moral panic on its way! | <collection> |

    Given I am logged in as "Salma Hayek Coster"
    When I visit the "<title>" "<content type>"
    Then the following fields should be present "Create comment, Post as anonymous"
    And I should see the button "Post comment"
    And I fill in "Create comment" with "<p>Mr anonymize was doing something<br />weird the other day.<p/>"
    And I check the box "Post as anonymous"
    When I press "Post comment"
    Then I should see the text "Anonymous user"

    When I click "Reply" in comment #1
    And I fill in "Create comment" with "Comment Anonymous indent 1"
    And I check the box "Post as anonymous"
    When I press "Post comment"
    Then comment #2 should contain the markup "Anonymous user"

    Given I am logged in as a moderator
    When I visit the "<title>" "<content type>"
    Then I should see the text "Anonymous user (Salma Coster)"
    Then comment #2 should contain the markup "Anonymous user"
    Then comment #2 should contain the markup "Salma Coster"

    Given I am an anonymous user
    When I visit the "<title>" "<content type>"
    Then I should see the text "Anonymous user"
    Then comment #2 should contain the markup "Anonymous user"

    Examples:
      | collection        | content type | title                     |
      | Show collection   | news         | Scandalous busy           |
      | Show collection   | discussion   | Is gossip bad or not?     |
      | Show collection   | document     | Wikilikes                 |
      | Gossip collection | news         | Bosinga wears pope outfit |
      | Gossip collection | discussion   | Is gossip good or not?    |
      | Gossip collection | document     | Celebrity scandals 2022   |
