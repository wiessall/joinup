@api @group-f
Feature: Private messages
  As an authenticated user
  I want to send a private message to another user
  So that I can communicate with them privately

  Background:
    Given users:
      | Username  | First name | Family name | E-mail                | Roles     |
      | alice_m   | Alice      | Miller      | alice.m@example.com   |           |
      | bob_rolls | Bob        | Rolls       | bob.rolls@example.com | moderator |
      | joan_tiff | Joan       | Tiff        | joan.tiff@example.com |           |

  @javascript
  Scenario: Send a private message
    Given I am logged in as "alice_m"
    When I am on the homepage
    Then I should see 0 unread private message
    When I am on "/user/messages"
    And I click "New message"
    Then a modal should open
    And I should see the text "New message" in the "Modal title"
    When I type "Bob Rolls" in the "To:" autocomplete field
    And I wait for AJAX to finish
    And I pick "Bob Rolls" from the "Member" autocomplete suggestions
    And I enter "A private message from Alice to Bob." in the "Message" wysiwyg editor
    And I press "Send" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the heading "Private Messages"
    And I should see 1 private message
    Then a message containing the text "A private message from Alice to Bob." should be present

    When I am logged in as "bob_rolls"
    And I am on the homepage
    And I should see 1 unread private message
    Then I open the account menu
    And I click "My messages"
    And I should see the heading "Private Messages"
    And I should see the link "New message"
    And I should not see the text "You do not have any messages"
    And I should see 1 private message
    And I should see the link "Delete thread"
    And a message containing the text "A private message from Alice to Bob." should be present
    When I enter "Another private message from Bob to Alice." in the "Reply" wysiwyg editor
    And I press "Send"
    Then I should see 2 private messages
    And I should see the following texts in private messages:
      | A private message from Alice to Bob.       |
      | Another private message from Bob to Alice. |
    When I click "New message"
    Then a modal should open
    When I type "Joan Tiff" in the "To:" autocomplete field
    And I wait until the page contains the text "Joan Tiff"
    And I pick "Joan Tiff" from the "Member" autocomplete suggestions
    And I enter "A private message from Bob to Joan." in the "Message" wysiwyg editor
    And I press "Send" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the following threads in private messages:
      | A private message from Bob to Joan.        |
      | Another private message from Bob to Alice. |
    When I click "Joan Tiff" in thread message card
    And I should see 1 private message
    Then I should see the following texts in private messages:
      | A private message from Bob to Joan. |
    Then I click "Alice Miller" in thread message card
    And I wait until the page contains the text "A private message from Alice to Bob."
    And I should see 2 private messages
    Then I should see the following texts in private messages:
      | A private message from Alice to Bob.       |
      | Another private message from Bob to Alice. |
    # Regression test for ISAICP-9130: second click impacts modal.
    Then I click "Alice Miller" in thread message card
    And I wait until the page contains the text "A private message from Alice to Bob."
    And I click "New message"
    Then a modal should open
    Then I close the modal dialog

    Given the limit for messages or threads allowed is 2
    When I click "New message"
    Then I should see "Sorry, you have reached your limit for posting. Please try again later."
    When I am on "/user/messages"
    Then I should not see "Sorry, you have reached your limit for posting. Please try again later."
    When I enter "This is a fail." in the "Reply" wysiwyg editor
    And I press "Send"
    Then I should see "Sorry, you have reached your limit for posting. Please try again later."
    Then I restore the limit for messages or threads allowed

    Given I am logged in as "alice_m"
    And I am on the homepage
    And I should see 1 unread private message
    Then I open the account menu
    And I click "My messages"
    Then a message containing the text "Another private message from Bob to Alice." should be present
    And I should not see the link "Delete thread"
    And I should not see "Delete message"
    But I should see the link "Delete for me"
    When I click "Delete for me"
    Then a modal should open
    And I should see the text "Delete conversation for me" in the "Modal title"
    And I should see the text "This action cannot be undone. The conversation will be removed from your view, but it will remain accessible to the other participant." in the "Modal content"
    And I should see the button "Delete for me" in the "Modal buttons" region
    Then I press "Delete for me" in the "Modal buttons" region
    And I should see 0 private message
    And I should see the text "You do not have any messages"

    # Check if Bob Rolls has the conversation.
    When I am logged in as "bob_rolls"
    And I am on the homepage
    Then I open the account menu
    And I click "My messages"
    And I should see the following threads in private messages:
      | Joan Tiff    |
      | Alice Miller |

    # Delete full thread.
    When I click "Joan Tiff" in thread message card
    When I click "Delete thread"
    Then a modal should open
    And I should see the text "Are you sure you want to delete the thread between you and the following users: Joan Tiff" in the "Modal title"
    And I should see the text "This action cannot be undone." in the "Modal content"
    And I should see the button "Delete thread" in the "Modal buttons" region
    Then I press "Delete thread" in the "Modal buttons" region
    And I should not see the text "Hello Bob message to delete"
    And I should see the following threads in private messages:
      | Alice Miller |

#    #Delete private message
    And a message containing the text "Another private message from Bob to Alice." should be present
    Then I should see "Delete message"
    Then I click "Delete message"
    And a modal should open
    And I should see the text "Are you sure you want to delete the private message" in the "Modal title"
    And I should see the text "This action cannot be undone." in the "Modal content"
    And I should see the button "Delete" in the "Modal buttons" region
    Then I press "Delete" in the "Modal buttons" region
    And a message containing the text "Another private message from Bob to Alice." should not be present

  Scenario: Check a user without messages
    Given I am logged in as "alice_m"
    And I am on the homepage
    And I should see 0 unread private message
    When I click "My messages" in the "Navigation bar" region
    Then I should see the heading "Private Messages"
    And I should see the link "New message"
    And I should see 0 private message
    And I should see the text "You do not have any messages"

  @javascript
  Scenario: User should see validation errors inside modal.
    Given I am logged in as "alice_m"

    When I am on "/user/messages"
    And I click "New message"
    When a modal should open
    And I press "Send" in the "Modal buttons" region

    Then I should see "Message field is required." in the "Modal content" region
    And I should see "You can not send a message to yourself only." in the "Modal content" region

  Scenario: User can view a selected thread.
    Given the following private messages:
      | owner   | recipient | message |
      | alice_m | bob_rolls | Hello 2 |
      | alice_m | bob_rolls | Hello 1 |

    When I am logged in as "alice_m"
    And I visit the "alice_m" "bob_rolls" thread
    Then I should see the following texts in private messages:
      | Hello 1 |
      | Hello 2 |
