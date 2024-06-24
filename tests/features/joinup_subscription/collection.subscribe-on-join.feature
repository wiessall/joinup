@api @group-e
Feature: Subscribing to a collection after joining
  In order to promote my collection
  As a collection owner
  I want to persuade new members to subscribe to my collection

  @javascript
  Scenario: Show a modal dialog asking a user to subscribe after joining
    Given collections:
      | title            | abstract                       | closed | description                          | state     |
      | Sapient Pearwood | Grows in magic-polluted areas  | no     | This tree is impervious to magic.    | published |
      | Drop bears       | Predator from the koala family | no     | Drops from a tree onto its prey.     | published |
      | Troll ducks      | Stone ducks don't float        | yes    | They sink to the bottom and walk.    | published |
      | Swamp dragons    | Explode when overexcited       | yes    | Bred for the hotness of their flame. | published |
    And users:
      | Username          |
      | Echinoid Blacksly |

    # Join an open collection.
    Given I am logged in as "Echinoid Blacksly"
    And I go to the homepage of the "Sapient Pearwood" collection
    When I press "Join this collection"
    Then I should see the success message "You are now a member of Sapient Pearwood."

    # A modal dialog opens proposing the user to subscribe to the collection.
    And a modal should open
    And I should see the text "Welcome to Sapient Pearwood" in the "Modal title"
    And I should see the text "You have joined the collection and you are now able to publish content in it." in the "Modal content"
    And I should see the text "Want to subscribe for new content notifications, too?" in the "Modal content"
    And I should see the text "You can update your notification preferences from your profile page at any time." in the "Modal content"
    And I should see the button "Don't subscribe" in the "Modal buttons" region
    And I should see the button "Subscribe to notifications" in the "Modal buttons" region

    # The user can decline the subscription.
    When I press "Don't subscribe" in the "Modal buttons" region
    Then the modal should be closed
    And I should not be subscribed to any bundle from the "Sapient Pearwood" collection

    # Navigate to another open collection, this time subscribing.
    When I go to the homepage of the "Drop bears" collection
    And I press "Join this collection"
    Then a modal should open
    # The modal uses animation to scroll into the view.
    # Since selenium does not know how to wait for this, we wait for the
    # animation to finish manually otherwise, the button is clicked too fast
    # and the modal is not dismissed.
    And I wait for 1 second
    When I press "Subscribe to notifications" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the success message "You have been subscribed to Drop bears and will receive weekly notifications. To manage your notifications go to My subscriptions in your user menu."
    And I should have the following content subscriptions:
      | Drop bears | discussion, document, event, news, solution, announcements |

    # Navigate to a closed collection and deny the subscription.
    When I go to the homepage of the "Troll ducks" collection
    And I press "Join this collection"
    Then a modal should open
    And I should see the text "Welcome to Troll ducks" in the "Modal title"
    And I should see the text "When your membership is approved you will be able to publish content in it." in the "Modal content"
    And I should see the text "Want to subscribe for new content notifications, too?" in the "Modal content"
    And I should see the text "You can update your notification preferences from your profile page at any time." in the "Modal content"
    And I should see the button "Don't subscribe" in the "Modal buttons" region
    And I should see the button "Subscribe to notifications" in the "Modal buttons" region
    When I press "Don't subscribe" in the "Modal buttons" region
    Then the modal should be closed
    And I should not be subscribed to any bundle from the "Troll ducks" collection

    # Navigate to a closed collection and accept the subscription.
    When I go to the homepage of the "Swamp dragons" collection
    And I press "Join this collection"
    Then a modal should open
    # Wait for the animation to finish.
    And I wait for 1 second
    When I press "Subscribe to notifications" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the success message "You have been subscribed to Swamp dragons and will receive weekly notifications once your membership is approved."
    And I should have the following content subscriptions:
      | Swamp dragons | discussion, document, event, news, solution, announcements |
