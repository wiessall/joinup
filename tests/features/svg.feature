@api @group-f
Feature: Test SVG attachments.

  Background:
    Given the following owner:
      | name | type                    |
      | SVG  | Non-Profit Organisation |
    And the following contact:
      | name  | SVG             |
      | email | svg@example.com |
    And collections:
      | title | logo     | banner     | abstract | description | content creation         | moderation | topic             | owner | contact information | state     |
      | SVG   | logo.png | banner.jpg | Test     | Test        | facilitators and authors | yes        | Supplier exchange | SVG   | SVG                 | validated |

  @terms @javascript @uploadFiles:error.svg,simple.svg
  Scenario: Edit a collection wysiwyg modal should close.
    When I am logged in as a facilitator of the "SVG" collection
    And I go to the edit form of the "SVG" collection

    And I press the button "Image" in the "Description" wysiwyg editor
    Then a modal should open
    When I fill in "Alternative text" with "Example SVG"
    And I attach the file "error.svg" to "Image"
    And I wait for AJAX to finish
    Then I should see the error message "Invalid or unsafe SVG file."
    And I attach the file "simple.svg" to "Image"
    And I wait for AJAX to finish
    Then I should not see the error message "Invalid or unsafe SVG file."
    And I press "Save" in the "Modal buttons" region
    Then the modal should be closed

    # Ensure local svg files are allowed.
    And I press "Publish"
    And I click "About"
    And the response should not contain "This image has been removed. For security reasons, only images from the local domain are allowed."
    And the response should contain "inline-images/simple"

  @terms @uploadFiles:error.svg,simple.svg
  Scenario: SVG logos appear in the tiles.
    When I am logged in as a facilitator of the "SVG" collection
    And I go to the homepage of the "SVG" collection
    And I click "Add news" in the plus button menu
    When I fill in the following:
      | Short title      | SVG News |
      | Headline         | SVG News|
      | Content          | SVG News |
    And I select "EU and European Policies" from "Topic"

    And I attach the file "simple.svg" to "Logo"
    And I press "Upload"
    And I press "Publish"
    Then I should see the heading "SVG News"

    When I go to the homepage of the "SVG" collection
    Then I should see the image "simple.svg"
