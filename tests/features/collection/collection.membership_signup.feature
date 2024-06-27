@api @joinup_collection @group-h @javascript
Feature: Tests membership to Joinup collection.

  Background:
    Given the following collections:
      | title                                 | state     | closed |
      | Open collection                       | published | no     |
      | Open collection with public CoC       | published | no     |
      | Closed collection                     | published | yes    |
      | Closed collection with public CoC     | published | yes    |
      | Closed collection with non-public CoC | published | yes    |
    Given custom_page content:
      | title                           | collection                            | state     |
      | Public CoC of Open collection   | Open collection with public CoC       | published |
      | Public CoC of Closed collection | Closed collection with public CoC     | published |
      | Draft CoC of Closed collection  | Closed collection with non-public CoC | draft     |
    And the "code of conduct" setting of the "Open collection with public CoC" collection is set to "Public CoC of Open collection" "custom page"
    And the "code of conduct" setting of the "Closed collection with public CoC" collection is set to "Public CoC of Closed collection" "custom page"
    And the "code of conduct" setting of the "Closed collection with non-public CoC" collection is set to "Draft CoC of Closed collection" "custom page"

  Scenario Outline: As a user I can join an open collection
    Given I am logged in as an "authenticated user"
    When I go to the homepage of the "<collection>" collection
    Then I should see the button "Join this collection" in the Header region
    When I press "Join this collection" in the Header region
    Then a modal should open
    Then I should see the link "Leave this collection" in the Header region
    And I should see the following lines of text:
      | You have joined the collection and you are now able to publish content in it.    |
      | Want to subscribe for new content notifications, too?                            |
      | You can update your notification preferences from your profile page at any time. |
    And the buttons in the "Modal buttons" region should not be disabled:
      | Subscribe to notifications |
      | Don't subscribe            |
    And I press "Subscribe to notifications" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the link "Leave this collection" in the Header region
    And I should see the following success messages:
      | Success messages                                                                                                                                        |
      | You have been subscribed to <collection> and will receive weekly notifications. To manage your notifications go to My subscriptions in your user menu. |
      | You are now a member of <collection>.                                                                                                                   |
    And I should have the following content subscriptions:
      | <collection> | announcements, discussion, document, event, news, solution |

    Examples:
      | collection                      |
      | Open collection                 |
      | Open collection with public CoC |

  Scenario Outline: As a user I can ask for a membership in a closed collection
    Given I am logged in as an "authenticated user"
    When I go to the homepage of the "<collection>" collection
    Then I should see the button "Join this collection" in the Header region
    When I press "Join this collection" in the Header region
    Then a modal should open
    Then I should see the link "Membership is pending" in the Header region
    And I should see the following lines of text:
      | When your membership is approved you will be able to publish content in it.      |
      | Want to subscribe for new content notifications, too?                            |
      | You can update your notification preferences from your profile page at any time. |
    And the buttons in the "Modal buttons" region should not be disabled:
      | Subscribe to notifications |
      | Don't subscribe            |
    And I press "Subscribe to notifications" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the link "Membership is pending" in the Header region
    And I should see the following success messages:
      | Success messages                                                                                                  |
      | You have been subscribed to <collection> and will receive weekly notifications once your membership is approved. |
      | Your membership to the <collection> collection is under approval.                                                |
    And I should have the following content subscriptions:
      | <collection> | announcements, discussion, document, event, news, solution |

    Examples:
      | collection                            |
      | Closed collection                     |
      | Closed collection with non-public CoC |

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
