@api
Feature: Test SVG attachments.

  Background:
    Given collection content:
      | title | content_creation         | group_moderation |
      | SVG   | facilitators and authors | yes              |

  @javascript
  Scenario: Edit a collection wysiwyg modal should close.
    When I am logged in as a facilitator of the "SVG" collection
    And I go to the edit form of the "SVG" collection

    And I press the button "Insert Media" in the "Description" wysiwyg editor
    Then a modal should open

    When I attach the file "error.svg" to "Add file"
    Then I should see the following inline form error messages:
      | Error messages                                      |
      | The specified file error.svg could not be uploaded. |
      | Invalid or unsafe SVG file.                         |

    But I attach the file "simple.svg" to "Add file"
    When I fill in "Alternative text" with "Example SVG" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region

    When I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed

    # Ensure local SVG files are allowed.
    When I press "Publish"
    Then the response should not contain "This image has been removed. For security reasons, only images from the local domain are allowed."
    But the response should contain "sites/default/files/media/image/simple.svg"

  Scenario: SVG logos appear in the tiles.
    When I am logged in as a facilitator of the "SVG" collection
    And I go to the homepage of the "SVG" collection
    And I click "Add discussion" in the plus button menu
    When I fill in the following:
      | Title   | SVG discussion |
      | Content | SVG discussion |
    And I select "EU and European Policies" options from "Topic"

    And I attach the file "simple.svg" to "Logo"
    And I press "Upload"
    And I press "Publish"
    Then I should see the heading "SVG discussion"

    When I go to the discussions page of "SVG"
    Then I should see the image "simple.svg"
