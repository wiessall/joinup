@api @group-f
Feature: Private messages
  As an authenticated user
  I want to send a private message to another user
  So that I can communicate with them privately

  Background:
    Given the following users:
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
    And I pick "Bob Rolls" from the "Member" autocomplete suggestions
    And I fill in "Message" with "A private message from Alice to Bob."
    And I press "Send" in the "Modal buttons" region
    And I wait for AJAX to finish
    And I go to "/user/messages"
    And I should see the heading "Private Messages"
    And I should see 1 private message
    And a message containing the text "A private message from Alice to Bob." should be present
    And I see the text "Conversation with Bob Rolls"

    When I am logged in as "bob_rolls"
    And I am on the homepage
    Then I should see 1 unread private message
    When I open the account menu
    And I click "My messages (1)"
    Then I should see the heading "Private Messages"
    And I should see the link "New message"
    And I should not see the text "You do not have any messages"
    And I should see 1 private message
    And a message containing the text "A private message from Alice to Bob." should be present
    When I fill in "Reply" with "Another private message from Bob to Alice."
    And I press "Send"
    Then I should see 2 private messages
    And I see the following lines of text in the correct order in "Private messages conversation":
      | A private message from Alice to Bob.       |
      | Another private message from Bob to Alice. |
    When I click "New message"
    Then a modal should open
    When I type "Joan Tiff" in the "To:" autocomplete field
    And I pick "Joan Tiff" from the "Member" autocomplete suggestions
    And I fill in "Message" with "A private message from Bob to Joan."
    And I press "Send" in the "Modal buttons" region
    And I wait for AJAX to finish
    And I go to "/user/messages"
    And I see the following lines of text in the correct order in "Private messages inbox":
      | A private message from Bob to Joan.        |
      | Another private message from Bob to Alice. |
    When I click "Joan Tiff" in thread message card
    Then I should see 1 private message
    And I see the following lines of text in the correct order in "Private messages conversation":
      | A private message from Bob to Joan. |
    When I click "Alice Miller" in thread message card
    And I wait until the page contains the text "A private message from Alice to Bob."
    Then I should see 2 private messages
    And I see the following lines of text in the correct order in "Private messages conversation":
      | A private message from Alice to Bob.       |
      | Another private message from Bob to Alice. |
    # Regression test for ISAICP-9130: second click impacts modal.
    When I click "Alice Miller" in thread message card
    And I wait until the page contains the text "A private message from Alice to Bob."
    And I click "New message"
    Then a modal should open
    Then I close the modal dialog

    Given the limit for messages or threads allowed is 2
    When I click "New message"
    Then I should see "Sorry, you have reached your limit for posting. Please try again later."
    When I am on "/user/messages"
    Then I should not see "Sorry, you have reached your limit for posting. Please try again later."
    And I fill in "Reply" with "This is a fail."
    And I press "Send"
    Then I should see "Sorry, you have reached your limit for posting. Please try again later."
    Then I restore the limit for messages or threads allowed

    # Test the relevant links.
    Given I am logged in as "alice_m"
    And I am on the homepage
    And I should see 1 unread private message
    Then I open the account menu
    And I click "My messages (1)"
    Then a message containing the text "Another private message from Bob to Alice." should be present
    And I should not see the link "Delete thread"
    And I should not see "Delete message"
    And I should not visibly see the link "Inbox"
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
    And I see the following lines of text in the correct order in "Private messages inbox":
      | Joan Tiff    |
      | Alice Miller |

    # Delete a single private message.
    When I click "Alice Miller" in thread message card
    Then a message containing the text "A private message from Alice to Bob." should be present
    And I should see a ".private_message_delete[title='Delete']" element
    When I click "Delete message"
    Then a modal should open
    And I should see the text "Are you sure you want to delete the private message" in the "Modal title"
    And I should see the text "This action cannot be undone." in the "Modal content"
    And I should see the button "Delete" in the "Modal buttons" region
    Then I press "Delete" in the "Modal buttons" region
    Then the modal should be closed
    And a message containing the text "A private message from Alice to Bob." should not be present
    And a message containing the text "Another private message from Bob to Alice." should be present
    When I click "Delete message"
    Then a modal should open
    Then I press "Delete" in the "Modal buttons" region
    Then the modal should be closed
    And a message containing the text "Another private message from Bob to Alice." should not be present
    And a message containing the text "A private message from Bob to Joan." should be present

    # Delete full thread.
    When I click "Joan Tiff" in thread message card
    And I should see the text "A private message from Bob to Joan"
    When I click "Delete thread"
    Then a modal should open
    And I should see the text "Are you sure you want to delete the thread between you and the following users: Joan Tiff" in the "Modal title"
    And I should see the text "This action cannot be undone." in the "Modal content"
    And I should see the button "Delete thread" in the "Modal buttons" region
    Then I press "Delete thread" in the "Modal buttons" region
    And I should not see the text "A private message from Bob to Joan"

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
  Scenario: Check a the collapse of Inbox in different resolutions.
    Given the following private messages:
      | owner      | recipient | message                  |
      | bob_rolls  | alice_m   | This is message from bob |

    When I am logged in as "alice_m"
    And I am on "/user/messages"
    Then I should see the link "Delete for me"
    # Test Inbox with a small display.
    # We use "Delete for me" to test if the Inbox is visible.
    When I use a device with a "small" display
    Then I should see the link "Inbox"
    But I should not visibly see the link "Delete for me"
    When I click "Inbox"
    Then I should see the link "Delete for me"
    Then I click "Bob Rolls" in thread message card
    And I wait for CSS animations to finish
    Then I should not visibly see the link "Delete for me"
    # Go back to normal display.
    Then I use a device with an "extra large" display
    Then I should not visibly see the link "Inbox"
    Then I should see the link "Delete for me"

  @javascript
  Scenario: User should see validation errors inside modal.
    Given I am logged in as "alice_m"
    When I am on "/user/messages"
    And I click "New message"
    Then a modal should open
    When I press "Send" in the "Modal buttons" region
    Then I should see "Invalid user." in the "Modal content" region
    And I should see "Message field is required." in the "Modal content" region

  @javascript
  Scenario: User can view a selected thread.
    Given the following private messages:
      | owner     | recipient | message |
      | alice_m   | bob_rolls | Hello 1 |
      | bob_rolls | alice_m   | Hello 2 |
      | alice_m   | bob_rolls | Hello 3 |

    When I am logged in as "alice_m"
    And I visit the "alice_m" "bob_rolls" thread
    Then I see the following lines of text in the correct order in "Private messages conversation":
      | Hello 1 |
      | Hello 2 |
      | Hello 3 |
    Then I see the following lines of text in the correct order in "Private messages conversation":
      | You       |
      | Bob Rolls |
      | You       |
    When I am logged in as "bob_rolls"
    And I visit the "alice_m" "bob_rolls" thread
    Then I see the following lines of text in the correct order in "Private messages conversation":
      | Alice Miller |
      | You          |
      | Alice Miller |
    And the page meets accessibility standards
