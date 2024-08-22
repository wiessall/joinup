@api @group-g
Feature: Subscribing to a collection after creating
  In order to subscribe my collection

  Scenario: Check my subscription after create a new collection.

    Given I am logged in as an authenticated
    When I go to the propose collection form
    When I fill in the following:
      | Title       | Rollerblade and Ancient Mythology                                                                      |
      | Abstract    | The seminal work on the ancient mythologies of the primitive and classical peoples of the Rollerblade. |
      | Description | The seminal work on the ancient mythologies of the primitive and classical peoples of the Rollerblade. |
      # Contact information data.
      | Name        | Larry Jaoe                                                                                              |
      | E-mail      | larry.jaoe@example.com                                                                                  |
    When I select "EU and European Policies" from "Topic"

    # Click the button to create an organisation owner.
    And I press "Add new" at the "Owner" field
    When I check the box "Company"
    And I fill in "Name" with "Acme"
    And I press "Create owner"

    When I press "Save"
    Then I should have the following content subscriptions:
      | Rollerblade and Ancient Mythology | discussion, document, event, news, solution, announcements |

    # Cleanup.
    And I delete the "Rollerblade and Ancient Mythology" collection
