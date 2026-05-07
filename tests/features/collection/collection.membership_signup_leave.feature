@api @group-d
Feature: Joining and leaving collections through the web interface
  In order to participate in the activities of a collection
  As an authenticated user
  I need to be able to join and leave collections

  @javascript
  Scenario Outline: As a user I can join collections without published Code of Conduct, and are also able to leave it
    Given the following "collection" content:
      | title                                 | closed |
      | Open collection                       | no     |
      | Open collection with public CoC       | no     |
      | Closed collection                     | yes    |
      | Closed collection with non-public CoC | yes    |
    And the following "custom_page" content:
      | title                          | collection                            | state     |
      | Public CoC of Open collection  | Open collection with public CoC       | published |
      | Draft CoC of Closed collection | Closed collection with non-public CoC | draft     |
    And the "Code of Conduct" setting of the "Open collection with public CoC" collection is set to "Public CoC of Open collection" "custom page"
    And the "Code of Conduct" setting of the "Closed collection with non-public CoC" collection is set to "Draft CoC of Closed collection" "custom page"
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
      | You can update your notification preferences from your profile page at any time. |
    And I <checkbox action> "Notify me when content is published in <collection>"
    And I press "Confirm" in the "Modal buttons" region
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
      | collection                            | checkbox action | state link after click | success message after click                                                                | modal text                                                                  | subscription expectation                                                             | expected final success message                                                                                                                            |
      | Open collection                       | check           | Leave this collection  | You are now a member of Open collection.                                                   | You're now an active member of this space and ready to participate fully!   | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Open collection and will receive weekly notifications. To manage your notifications go to My subscriptions in your user menu. |
      | Open collection                       | uncheck         | Leave this collection  | You are now a member of Open collection.                                                   | You're now an active member of this space and ready to participate fully!   | should not be subscribed to any                                                      | You are now a member of Open collection.                                                                                                                  |
      | Open collection with public CoC       | uncheck         | Leave this collection  | You are now a member of Open collection with public CoC.                                   | You're now an active member of this space and ready to participate fully!   | should not be subscribed to any                                                      | You are now a member of Open collection with public CoC.                                                                                                  |
      | Closed collection                     | check           | Membership is pending  | Your membership to the Closed collection collection is under approval.                     | When your membership is approved you will be able to publish content in it. | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Closed collection and will receive weekly notifications once your membership is approved.                                     |
      | Closed collection                     | uncheck         | Membership is pending  | Your membership to the Closed collection collection is under approval.                     | When your membership is approved you will be able to publish content in it. | should not be subscribed to any                                                      | Your membership to the Closed collection collection is under approval.                                                                                    |
      | Closed collection with non-public CoC | check           | Membership is pending  | Your membership to the Closed collection with non-public CoC collection is under approval. | When your membership is approved you will be able to publish content in it. | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Closed collection with non-public CoC and will receive weekly notifications once your membership is approved.                 |
      | Closed collection with non-public CoC | uncheck         | Membership is pending  | Your membership to the Closed collection with non-public CoC collection is under approval. | When your membership is approved you will be able to publish content in it. | should not be subscribed to any                                                      | Your membership to the Closed collection with non-public CoC collection is under approval.                                                                |

  @javascript
  Scenario Outline: As a user I can ask for a membership in a closed collection with a Code of Conduct document. I have to accept CoC.
    Given the following "collection" content:
      | title                             | closed |
      | Closed collection with public CoC | yes    |
    And the following "custom_page" content:
      | title                           | collection                        |
      | Public CoC of Closed collection | Closed collection with public CoC |
    And the "Code of Conduct" setting of the "Closed collection with public CoC" collection is set to "Public CoC of Closed collection" "custom page"
    Given I am logged in as an "authenticated user"
    When I go to the homepage of the "Closed collection with public CoC" collection
    Then I should see the button "Join this collection" in the Header region
    When I press "Join this collection" in the Header region
    Then a modal should open
    # There is a Code of Conduct document, what the user must accept before
    # requesting for membership, so the button text must not change.
    Then I should see the button "Join this collection" in the Header region
    And I should see the text "To validate your membership and send it for approval, please tick the box agreeing with the Code of Conduct. This helps us maintain a safe and respectful environment for all members of Closed collection with public CoC"
    And I should not see the following lines of text:
      | You're now an active member of this space and ready to participate fully!   |
      | When your membership is approved you will be able to publish content in it. |
    And I should see the link "Public CoC of Closed collection"
    Then I check "I have read and agree to the Code of Conduct of Closed collection with public CoC."
    And I <checkbox action> "Notify me when content is published in Closed collection with public CoC."
    And I press "Confirm" in the "Modal buttons" region
    Then the modal should be closed
    And I should not see the button "Join this collection" in the Header region
    But I should see the link "Membership is pending" in the Header region
    And I should see the success message "<expected success message>"
    And I <subscription expectation> bundles from the "Closed collection with public CoC" collection
    And the "Closed collection with public CoC" collection should have 1 pending members
    When I am logged in as a user with the "authenticated" role
    When I go to the homepage of the "Closed collection with public CoC" collection
    And I press "Join this collection" in the Header region
    Then a modal should open
    Given I <checkbox action> "I have read and agree to the Code of Conduct of Closed collection with public CoC."
    And I press Cancel in the "Modal buttons" region
    Then the modal should be closed
    And I should see the button "Join this collection" in the Header region
    And the "Closed collection with public CoC" collection should have 1 pending members

    Examples:
      | checkbox action | subscription expectation                                                             | expected success message                                                                                                              |
      | check           | should be subscribed to "announcements, discussion, document, event, news, solution" | You have been subscribed to Closed collection with public CoC and will receive weekly notifications once your membership is approved. |
      | uncheck         | should not be subscribed to any                                                      | You will get a notification once your membership at Closed collection with public CoC is approved.                                    |

  Scenario: A collection owner leaving the collection cannot administer users anymore. Joining and leaving labels could be changed.
    Given the following users:
      | Username         |
      | Collection owner |
      | Stewe Griffin    |
    Given the following "collection" content:
      | title             | closed | author           |
      | Open collection 2 | no     | Collection owner |
    And the following collection user memberships:
      | collection        | user          | roles |
      | Open collection 2 | Stewe Griffin |       |
    Given I am logged in as "Collection owner"
    When I go to the homepage of the "Open collection 2" collection
    And I open the header local tasks menu
    And I click "Settings" in the "Entity actions" region
    And I fill in "Join label" with "Join this community"
    And I fill in "Leave label" with "Leave this community"
    Then the following field should not be present "Code of Conduct"
    And I press "Save"
    And I click "Leave this community"

    # The collection owner cannot leave the collection before transferring the rights to another owner.
    Then I should see the text "You are owner of this collection. Before you leave this collection, you should transfer the ownership to another member."
    And I should not see the button "Confirm"

    When I go to the members page of "Open collection 2"
    And I select the "Stewe Griffin" row
    And I select "Transfer the ownership of the collection to the selected member" from "Action"
    And I press "Apply to selected items"
    And I press "Confirm"
    Then I should see the text "Ownership of Open collection 2 collection transferred from user Collection owner to Stewe Griffin."

    When I go to the homepage of the "Open collection 2" collection
    And I click "Leave this community"
    And I press "Confirm"

    When I click "1 member"
    Then I should not see the link "Add members"
    When I go to the homepage of the "Open collection 2" collection
    Then I should see the "Join this community" button
