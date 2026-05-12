@api @group-e
Feature: "Add document" visibility options.
  In order to manage documents
  As a solution member
  I need to be able to add "Document" content through UI.

  Scenario: "Add document" button should not be shown to normal members, authenticated users and anonymous users.
    Given the following "collection" content:
      | title                   | logo     |
      | Collective Seventh Name | logo.png |
    Given the following "solution" content:
      | title               | collection              | logo     |
      | Seventh Name        | Collective Seventh Name | logo.png |
      | The Obsessed Stream | Collective Seventh Name | logo.png |

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Seventh Name" solution
    Then I should not see the link "Add document"

    When I am an anonymous user
    And I go to the homepage of the "Seventh Name" solution
    Then I should not see the link "Add document"

    When I am logged in as a "facilitator" of the "Seventh Name" solution
    And I go to the homepage of the "Seventh Name" solution
    Then I should see the link "Add document"
    # I should not be able to add a document to a different solution
    When I go to the homepage of the "The Obsessed Stream" solution
    Then I should not see the link "Add document"

    When I am logged in as a "moderator"
    And I go to the homepage of the "Seventh Name" solution
    Then I should see the link "Add document"

  Scenario: Add document as a facilitator.
    Given the following "collection" content:
      | title                          | logo     |
      | Collective Winter of Beginning | logo.png |
    Given the following "solution" content:
      | title               | collection                     | logo     |
      | Winter of Beginning | Collective Winter of Beginning | logo.png |
    And I am logged in as a facilitator of the "Winter of Beginning" solution

    When I go to the homepage of the "Winter of Beginning" solution
    And I click "Add document" in the plus button menu
    Then I should see the heading "Add document"
    And the following fields should be present "Title, Short title, Abstract, Body, Type, Topic, Search keywords, Geographical coverage, Licence, Documentation"
    And the following fields should not be present "Shared on, Motivation"

    When I fill in the following:
      | Title       | The Sparks of the Butterfly |
      | Short title | Amazing document            |
      | Abstract    | Amazing document            |
    And I select "Document" from "Type"
    And I press "Save as draft"
    Then I should see the following inline form error messages:
      | error messages                 |
      | Topic field is required.       |
      | This value should not be null. |

    When I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | This is going to be an amazing document. |
      | field_body:format | content_editor                           |
    And I attach the file "test1.zip" to "files[resources_0_subform_resource_file_0]"
    And I select "EU and European Policies" options from "Topic"
    And I press "Save as draft"
    Then I should see the heading "The Sparks of the Butterfly"
    And I should see the success message 'Document The Sparks of the Butterfly has been created as draft. You can find it in the section "My unpublished content" located in your My account page, or in the aforementioned section under the Solution it was created in.'
    And I should see the link "test1.zip"
    And the "Winter of Beginning" solution has a document titled "The Sparks of the Butterfly"
