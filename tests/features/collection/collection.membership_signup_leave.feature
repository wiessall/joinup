@api @group-a
Feature: Joining and leaving collections through the web interface
  In order to participate in the activities of a collection
  As an authenticated user
  I need to be able to join and leave collections

  Background:
    Given users:
      | Username          |
      | Collection author |
      | Stewe Griffin     |
    Given the following collections:
      | title                                 | state     | closed | author            |
      | Open collection                       | published | no     | Collection author |
      | Open collection with public CoC       | published | no     |                   |
      | Closed collection                     | published | yes    |                   |
      | Closed collection with public CoC     | published | yes    |                   |
      | Closed collection with non-public CoC | published | yes    |                   |
    Given custom_page content:
      | title                           | collection                            | state     |
      | Public CoC of Open collection   | Open collection with public CoC       | published |
      | Public CoC of Closed collection | Closed collection with public CoC     | published |
      | Draft CoC of Closed collection  | Closed collection with non-public CoC | draft     |
    And the following collection user memberships:
      | collection      | user              | roles             |
      | Open collection | Collection author | owner             |
      | Open collection | Stewe Griffin     |                   |
    And the "code of conduct" setting of the "Open collection with public CoC" collection is set to "Public CoC of Open collection" "custom page"
    And the "code of conduct" setting of the "Closed collection with public CoC" collection is set to "Public CoC of Closed collection" "custom page"
    And the "code of conduct" setting of the "Closed collection with non-public CoC" collection is set to "Draft CoC of Closed collection" "custom page"

  @javascript
  Scenario Outline: As a user I can join collections without published Code of Conduct, and are also able to leave it
    Given I am logged in as an "authenticated user"
    When I go to the homepage of the "<collection>" collection
    When I press "Join this collection" in the Header region
    Then a modal should open
    Then I should see the link "<state link after click>" in the Header region
    And I should see the following success messages:
      | Success messages              |
      | <success message after click> |
    And I should see the following lines of text:
      | <modal text>                                                                     |
      | Want to subscribe for new content notifications, too?                            |
      | You can update your notification preferences from your profile page at any time. |
    And the buttons in the "Modal buttons" region should not be disabled:
      | Subscribe to notifications |
      | Don't subscribe            |
    And I press "<button to click>" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the success message "<expected final success message>"
    And I <subscription expectation> bundles from the "<collection>" collection

    # Leaving the collection.
    # @todo It currently is not possible to cancel a pending membership, so for
    #   the moment we approve the membership and then leave the collection as a
    #   normal member. When ISAICP-3658 is implemented this should be replaced
    #   with a test for the cancellation of a pending membership.
    # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3658
    Given my membership state in the "<collection>" collection changes to "active"
    Given I go to the homepage of the "<collection>" collection
    And I click "Leave this collection"
    Then a modal should open
    And I should see the text "Are you sure you want to leave the <collection> collection?" in the "Modal content" region
    When I press "Confirm" in the "Modal buttons" region
    Then I should see the success message "You are no longer a member of <collection>."
    And I should see the "Join this collection" button

    Examples:
      | collection                            | button to click            | state link after click | success message after click                                                                | modal text                                                                    | subscription expectation                                                             | expected final success message                                                                                                                            |
      | Open collection                       | Subscribe to notifications | Leave this collection  | You are now a member of Open collection.                                                   | You have joined the collection and you are now able to publish content in it. | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Open collection and will receive weekly notifications. To manage your notifications go to My subscriptions in your user menu. |
      | Open collection                       | Don't subscribe            | Leave this collection  | You are now a member of Open collection.                                                   | You have joined the collection and you are now able to publish content in it. | should not be subscribed to any                                                      | You are now a member of Open collection.                                                                                                                  |
      | Open collection with public CoC       | Don't subscribe            | Leave this collection  | You are now a member of Open collection with public CoC.                                   | You have joined the collection and you are now able to publish content in it. | should not be subscribed to any                                                      | You are now a member of Open collection with public CoC.                                                                                                  |
      | Closed collection                     | Subscribe to notifications | Membership is pending  | Your membership to the Closed collection collection is under approval.                     | When your membership is approved you will be able to publish content in it.   | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Closed collection and will receive weekly notifications once your membership is approved.                                     |
      | Closed collection                     | Don't subscribe            | Membership is pending  | Your membership to the Closed collection collection is under approval.                     | When your membership is approved you will be able to publish content in it.   | should not be subscribed to any                                                      | Your membership to the Closed collection collection is under approval.                                                                                    |
      | Closed collection with non-public CoC | Subscribe to notifications | Membership is pending  | Your membership to the Closed collection with non-public CoC collection is under approval. | When your membership is approved you will be able to publish content in it.   | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Closed collection with non-public CoC and will receive weekly notifications once your membership is approved.                 |
      | Closed collection with non-public CoC | Don't subscribe            | Membership is pending  | Your membership to the Closed collection with non-public CoC collection is under approval. | When your membership is approved you will be able to publish content in it.   | should not be subscribed to any                                                      | Your membership to the Closed collection with non-public CoC collection is under approval.                                                                |

  @javascript
  Scenario Outline: As a user I can ask for a membership in a closed collection with a Code of Conduct document
    Given I am logged in as an "authenticated user"
    When I go to the homepage of the "Closed collection with public CoC" collection
    Then I should see the button "Join this collection" in the Header region
    When I press "Join this collection" in the Header region
    Then a modal should open
    # There is a Code of Conduct document, what the user must accept before
    # requesting for membership, so the button text must not change.
    Then I should see the button "Join this collection" in the Header region
    And I should not see the text "To complete your application and send it for approval, we need to make sure you agree with our Public CoC of Closed collection. This helps us maintain a safe and respectful environment for all our members."
    And I should see the link "Public CoC of Closed collection"
    Then the "I have read and agree to the Public CoC of Closed collection." checkbox should not be checked
    And the buttons in the "Modal buttons" region should be disabled:
      | Subscribe to notifications |
      | Don't subscribe            |
    And the buttons in the "Modal buttons" region should not be disabled:
      | Cancel                     |
    And I check "I have read and agree to the Public CoC of Closed collection."
    Then the buttons in the "Modal buttons" region should not be disabled:
      | Subscribe to notifications |
      | Don't subscribe            |
      | Cancel                     |
    And I press "<button to click>" in the "Modal buttons" region
    Then the modal should be closed
    And I should not see the button "Join this collection" in the Header region
    But I should see the link "Membership is pending" in the Header region
    And I should see the success message "<expected success message>"
    And I <subscription expectation> bundles from the "Closed collection with public CoC" collection

    Examples:
      | button to click            | subscription expectation                                                             | expected success message                                                                                                              |
      | Subscribe to notifications | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Closed collection with public CoC and will receive weekly notifications once your membership is approved. |
      | Don't subscribe            | should not be subscribed to any                                                      | You will get a notification once your membership at Closed collection with public CoC is approved.                                    |

  @javascript
  Scenario Outline: No membership is created when pressing Cancel on a modal of a closed collection with CoC
    Given I am logged in as an "authenticated user"
    When I go to the homepage of the "Closed collection with public CoC" collection
    And I press "Join this collection" in the Header region
    Then a modal should open
    Given I <checkbox action> "I have read and agree to the Public CoC of Closed collection."
    And I press Cancel in the "Modal buttons" region
    Then the modal should be closed
    And I should see the button "Join this collection" in the Header region
    And the "Closed collection with public CoC" collection should have 0 active members
    And the "Closed collection with public CoC" collection should have 0 pending members
    And the "Closed collection with public CoC" collection should have 0 blocked members

    Examples:
      | checkbox action |
      | check           |
      | uncheck         |

  Scenario: A collection owner leaving the collection cannot administer users anymore.
    Given I am logged in as "Collection author"
    When I go to the homepage of the "Open collection" collection
    And I click "Leave this collection"

    # The collection owner cannot leave the collection before transferring the rights to another owner.
    Then I should see the text "You are owner of this collection. Before you leave this collection, you should transfer the ownership to another member."
    And I should not see the button "Confirm"

    When I go to the members page of "Open collection"
    And I select the "Stewe Griffin" row
    And I select "Transfer the ownership of the collection to the selected member" from "Action"
    And I press "Apply to selected items"
    And I press "Confirm"
    Then I should see "Ownership of Open collection collection transferred from user Collection author to Stewe Griffin."

    When I go to the homepage of the "Open collection" collection
    And I click "Leave this collection"
    And I press "Confirm"

    When I click "1 member"
    Then I should not see the link "Add members"

  Scenario: Custom labels for Joining and leaving a collection.
    Given I am logged in as a user with the "moderator" role
    And I go to the homepage of the "Open collection" collection
    And I open the header local tasks menu
    And I click "Settings" in the "Entity actions" region
    And I fill in "Join label" with "Join this community"
    And I fill in "Leave label" with "Leave this community"
    And I press "Save"

    When I am logged in as a user with the "authenticated" role
    And I go to the homepage of the "Open collection" collection
    Then I should see the "Join this community" button

    When I press "Join this community"
    And I press "Don't subscribe"
    Then I should see the link "Leave this community"

  @javascript
  Scenario: Close the modal dialogs with the cancel button.
    # Anonymous users can cancel the "Authenticate to join" modal.
    Given I am an anonymous user
    And I go to the homepage of the "Open collection" collection
    # This is actually a link which is styled as a button.
    When I click "Join this collection"
    Then a modal should open
    And I should see the text "Sign in to join"

    When I press "Cancel" in the "Modal buttons" region
    And I wait for AJAX to finish
    # Since this is a modal, the dialog simply closes instead of redirecting to
    # another page. This is why the collection title is still displayed.
    Then I should see the heading "Open collection"

    # Members can cancel the "Leave collection" modal.
    Given I am logged in as "Stewe Griffin"
    And I go to the homepage of the "Open collection" collection

    When I click "Leave this collection"
    Then a modal should open

    When I press "Cancel" in the "Modal buttons" region
    And I wait for AJAX to finish
    Then I should see the heading "Open collection"

  Scenario Outline: Only moderators are able to edit the Code of Conduct setting
    Given I am logged in as <user>
    And I go to the homepage of the "Open collection" collection
    And I click "Settings" in the "Entity actions" region
    Then the following field <Code of Conduct field> present "Code of Conduct"

    # Access to Settings is implicitly tested in
    # metadata_banner_disabled.feature, so we only test with these roles.
    Examples:
      | user                                              | Code of Conduct field |
      | "Collection author"                               | should not be         |
      | a facilitator of the "Open collection" collection | should not be         |
      | a moderator                                       | should be             |
