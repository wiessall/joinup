@api @terms
Feature:
  Ensure curated list of topics on the homepage behave properly.

  Scenario: Show list of topics meta entity type and check a given parent and published field.
    Given I am logged in as a moderator
    When I click "Dashboard"
    Then I should see the link Topics

    When I click Topics
    Then I should see the heading "Topics"
    And I should see "eGov" in the "Supplier exchange" row
    And I should see "Economy and Welfare" in the "Finance in EU" row
    And I should see "E-health Dpt." in the "E-health" row
    And I should see "HR Dpt." in the "Employment and Support Allowance" row
    And I should see "HR Dpt." in the "HR" row
    # The meta entity is unpublished by default.
    And I should see "No" in the "E-justice" row
    # Sorting by parent not allowed.
    And I should not see the link "Parent topic"

    When I click "Edit" in the "E-justice" row
    Then I should see the heading "Edit E-justice"
    And the checkbox "Published" should not be checked
    When I check "Published"
    And I press "Save"

    # The meta entity is now marked as published.
    And I should see "Yes" in the "E-justice" row
    And I should see the heading "Topics"

    # The following is not reproducible in the UI but is a backend test for
    # future changes in the topic vocabulary.
    Given "topic" terms:
      | tid                          | name        | description  |
      | http://some.topic/BlueParent | Blue parent | Hello there. |
    When I reload the page
    Then I should see the link "Blue parent"
    When the name of the "Blue parent" term is changed to "Red parent"
    And I reload the page
    Then I should see the link "Red parent"

  Scenario: Moderators can access and manage the topics curated queue.
    Given I am logged in as a moderator
    And I click "Dashboard"
    And I click "Curated content listings"
    And I click "Homepage topics"
    Then I should see the heading "Update curated content listing Homepage topics"
    And I should see the button "Add item"

    And I fill in "edit-items-add-more-new-item-target-id" with "E-justice"
    And I press "Add item"
    # 'E-justice' is unpublished so it cannot be found.
    Then I should see the error message 'There are no meta entity entities matching "E-justice".'

    # Publish 2 terms.
    When I click "Dashboard"
    And I click Topics
    And I click "Edit" in the "E-justice" row
    And I attach the file "ada.png" to "Image"
    And I press "Upload"
    Then I should see the success message "The image was resized to fit within the maximum allowed dimensions of 552x240 pixels. The new dimensions of the resized image are 153x240 pixels."
    And I should see the following error messages:
      | error messages                                                                                                                       |
      | The specified file ada.png could not be uploaded.                                                                                    |
      | The resized image is too small. The minimum dimensions are 552x240 pixels and after resizing, the image size will be 153x240 pixels. |
    And I attach the file "topic.png" to "Image"
    And I press "Upload"
    When I fill in "Description" with "E-justice description"
    And I check "Published"
    And I press "Save"
    And I click "Edit" in the "Finance in EU" row
    And I fill in "Description" with "Finance in EU description"
    And I check "Published"
    And I press "Save"

    # Add the 2 terms to the curated list.
    When I click "Dashboard"
    And I click "Curated content listings"
    And I click "Homepage topics"
    # The field does not have a title of a label element.
    And I fill in "edit-items-add-more-new-item-target-id" with "E-justice"
    And I press "Add item"
    And I fill in "edit-items-add-more-new-item-target-id" with "Finance in EU"
    And I press "Add item"
    And I press "Save"

    And the "form table" table should contain a column with the following data:
      # The 'Edit' keyword is because there is an edit link in the table cell.
      | E-justice Edit     |
      | Finance in EU Edit |

    When I go to the homepage
    Then I should see the text "E-justice" in the "Homepage topics" region
    And I should see the text "E-justice description" in the "Homepage topics" region
    And I should see the text "Finance in EU" in the "Homepage topics" region
    And I should see the text "Finance in EU description" in the "Homepage topics" region

    # Unpublish a term and check it is removed from the curated list.
    When I click "Dashboard"
    And I click Topics
    And I click "Edit" in the "E-justice" row
    And I uncheck "Published"
    And I press "Save"
    Then I should see the success message "The topic has been removed from the homepage topics curated list since it was unpublished."
    When I click "Dashboard"
    And I click "Curated content listings"
    And I click "Homepage topics"
    And the "form table" table should contain a column with the following data:
      | Finance in EU Edit |

    When I go to the homepage
    Then I should not see the text "E-justice" in the "Homepage topics" region
    But I should see the text "Finance in EU" in the "Homepage topics" region

  Scenario: Configurable listings are available for the topic meta entities.
    Given the following topics are enabled in the homepage:
      | E-justice  |
    And I am logged in as a moderator
    And I go to the "topic" meta entity of the "E-justice"
    And I click "E-justice"
    And I click "Edit"
    Then I should see the heading "Edit E-justice"
    And I should not see the button "Add configurable listing"
    When I press "Add 1 column layout"

    Given I reload the page
    And I press "Add 2 column layout"
    Then I should see the button "Add configurable listing"

  Scenario: Configurable listings are not available for custom pages.
    Given the following collection:
      | title | Currated  |
      | state | validated |
    And I am logged in as a moderator
    And I go to the "Currated" collection
    And I click "Add custom page"
    Then I should not see the button "Add configurable listing"
    When I press "Add 1 column layout"
    And I press "Add 2 column layout"
    Then I should not see the button "Add configurable listing"

  Scenario: Lists of configurable content can be added in the page of the topic meta entity.
    Given news content:
      | title  | state     |
      | News 1 | validated |
    Given the following topics are enabled in the homepage:
      | topic name |
      | E-justice  |
    And I am logged in as a moderator
    And I go to the homepage
    And I click "E-justice"
    And I click "Edit"
    Then I should see the heading "Edit E-justice"

    Given I press "Add 1 column layout"
    And I press "Add Configurable listing"
    Then the following fields should be present "Heading, Action link, List style, Query presets"

    When I fill in "Heading" with "E-justice heading"
    And I fill in "URL" with "http://example.com"
    And I fill in "Link text" with "E-justice link text"
    And I select "List" from "List style"
    And I fill in "Query presets" with "entity_bundle|news"

    And I press "Save"
    Then I should see the success message "The meta entity has been updated."
    And I should see the heading "E-justice"
    And I should see the text "E-Justice heading"
    And I should see the link "E-justice link text"
    # @todo: A separate step with the list items should be asserted here.
    # @see ISAICP-
    And I should see the "News 1" tile

    When I click "Edit"
    And I select "Grid" from "List style"
    And I press "Save"
    Then I should see the success message "The meta entity has been updated."
    And I should see the "News 1" tile
