@api @group-i
Feature:
  In order to efficiently manage users
  As a moderator of the website
  I need to be able to cancel user accounts

  Background:

    Given the following users:
      | Username     | Roles | E-mail                   | First name | Family name |
      | alicia__1997 |       | AliciaPotter@example.com | Alicia     | Potter      |

  Scenario: A moderator cancels a user account.
    Given I am logged in as a moderator
    And I click "People"
    And I click "Alicia Potter"
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I click "Cancel account"
    Then I should see the link "Go back"
    When I press "Confirm"
    And I wait for the batch process to finish
    And the following email should have been sent:
      | recipient_mail | AliciaPotter@example.com                                                                                                                                                                                                                                                                  |
      | subject        | Your account has been deleted.                                                                                                                                                                                                                                                            |
      | body           | Your account alicia__1997 has been deleted. This action has been done in the framework of moderation activities regularly conducted on the Interoperable Europe Portal platform. If you believe that this action has been performed by mistake, please contact The Portal Support Team at |
    And 1 e-mail should have been sent

  @deletedUser
  Scenario: A moderator deletes a user using the administrative UI.
    Given the following "collection" content:
      | title           |
      | Test collection |
    And the following "news" content:
      | title     | author       | collection      |
      | News item | alicia__1997 | Test collection |

    Given I am logged in as a moderator
    And I click "People"

    When I select the "Alicia Potter" row
    And I select "Cancel the selected user account(s) but keep their content" from "Action"
    And I press "Apply to selected items"

    Then I should see the heading "Are you sure you want to cancel these user accounts?"
    And I should see the text "Alicia Potter"

    When I press "Confirm"
    And I wait for the batch process to finish
    Then I should see the success message "Alicia Potter has been deleted."
    And I should not see the link "Alicia Potter"
    And the following email should have been sent:
      | recipient_mail | AliciaPotter@example.com                                                                                                                                                                                                                                                                  |
      | subject        | Your account has been deleted.                                                                                                                                                                                                                                                            |
      | body           | Your account alicia__1997 has been deleted. This action has been done in the framework of moderation activities regularly conducted on the Interoperable Europe Portal platform. If you believe that this action has been performed by mistake, please contact The Portal Support Team at |
    # We cannot assert the number of emails because of created content that
    # sends messages to administrators and moderators. Depending on environment
    # the list of recipients might differ. That's why we're only asserting that
    # a "status blocked" message has not been sent.
    And the following email should not have been sent:
      | template       | status_blocked                 |
      | recipient_mail | AliciaPotter@example.com       |
      | subject        | Your account was just blocked. |
    And the "Alicia Potter" user doesn't exist

    # The content created by an account deleted should still show the full name
    # of the author, but the author should not be clickable.
    When I go to "/collection/test-collection/news/news-item"
    Then the response status code should be 200
    And I should see the text "Alicia Potter"
    But I should not see the link "Alicia Potter"

  Scenario: Cancel own account.
    Given the following "collection" content:
      | title           |
      | Test collection |
    And the following "news" content:
      | title     | author       | collection      |
      | News item | alicia__1997 | Test collection |
    And the following "document" content:
      | title | author       | collection      |
      | Docky | alicia__1997 | Test collection |
    And the following "discussion" content:
      | title  | collection      |
      | Disqus | Test collection |
    And comments:
      | subject  | field_body   | author       | parent |
      | Awesome! | Let's use it | alicia__1997 | Disqus |

    # The content author is clickable.
    When I visit the "News item" news
    Then I should see the link "Alicia Potter"
    When I visit the "Docky" document
    Then I should see the link "Alicia Potter"
    # Check also the comment's author.
    When I visit the "Disqus" discussion
    Then I should see the link "Alicia Potter"

    Given I am logged in as "alicia__1997"
    And I visit "/user"
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I click "Cancel account"
    And I press "Confirm"
    Then the following email should have been sent:
      | recipient_mail | AliciaPotter@example.com                                                     |
      | subject        | Account cancellation request for alicia__1997 at Interoperable Europe Portal |
      | body           | by clicking this link or copying and pasting it into your browser:           |
    # Click the confirmation link in the email.
    When I click the delete confirmation link for the user "alicia__1997" from the last email
    And I wait for the batch process to finish
    Then the following email should have been sent:
      | recipient_mail | AliciaPotter@example.com                                                                                 |
      | subject        | Your account has been deleted.                                                                           |
      | body           | If you believe that this action has been performed by mistake, please contact The Portal Support Team at |

    # The content author is no more clickable.
    When I visit the "News item" news
    Then I should see the text "Alicia Potter"
    Then I should not see the link "Alicia Potter"
    When I visit the "Docky" document
    Then I should see the text "Alicia Potter"
    Then I should not see the link "Alicia Potter"
    When I visit the "Disqus" discussion
    Then I should see the text "Alicia Potter"
    Then I should not see the link "Alicia Potter"

  @deletedUser
  Scenario Outline:
    Given the following "collection" content:
      | title          |
      | Macroeconomics |
    Given the following "<content type>" content:
      | title                 | author       | collection     |
      | Canada hit by tariffs | alicia__1997 | Macroeconomics |
    And the following users:
      | Username | E-mail          | First name | Family name |
      | viviene  | viv@example.com | Viviene    | De Québec   |
    And comments:
      | message  | author  | parent                |
      | Too much | viviene | Canada hit by tariffs |

    # Authors of node & comment are linked because their accounts exist.
    When I visit the "Canada hit by tariffs" <content type>
    Then I should see the link "Alicia Potter"
    And I should see the link "Viviene De Québec"

    # Author of comment lacks a link because the account has been deleted.
    When I delete the "viviene" user
    And I reload the page
    Then I should see the text "Viviene De Québec"
    But I should not see the link "Viviene De Québec"

    # Authors of node & comment lack links because their accounts were deleted.
    When I delete the "alicia__1997" user
    And I reload the page
    Then I should see the text "Alicia Potter"
    But I should not see the link "Alicia Potter"
    And I should see the text "Viviene De Québec"
    But I should not see the link "Viviene De Québec"

    Examples:
      | content type |
      | discussion   |
      | document     |
      | event        |
      | news         |

  @deletedUser
  Scenario: Assessments are preserved when their author is deleted.
    Given report assessments:
      | name               | state     | uid          |
      | Climate Assessment | published | alicia__1997 |

    Then the report assessment with name "Climate Assessment" should exist
    When I delete the "alicia__1997" user
    Then the report assessment with name "Climate Assessment" should exist
