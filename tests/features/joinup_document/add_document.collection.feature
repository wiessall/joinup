@api @group-e
Feature: "Add document" visibility options.
  In order to manage documents
  As a collection member
  I need to be able to add "Document" content through UI.

  Scenario: "Add document" button should not be shown to normal members, authenticated users and anonymous users.
    Given the following "collection" content:
      | title                   | logo     |
      | Ring of Truth           | logo.png |
      | The Storms of the Waves | logo.png |

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Ring of Truth" collection
    Then I should not see the link "Add document"

    When I am an anonymous user
    And I go to the homepage of the "Ring of Truth" collection
    Then I should not see the link "Add document"

    When I am logged in as a member of the "Ring of Truth" collection
    And I go to the homepage of the "Ring of Truth" collection
    Then I should see the link "Add document"

    When I am logged in as a "facilitator" of the "Ring of Truth" collection
    And I go to the homepage of the "Ring of Truth" collection
    Then I should see the link "Add document"
    # I should not be able to add a document to a different collection
    When I go to the homepage of the "The Storms of the Waves" collection
    Then I should not see the link "Add document"

    When I am logged in as a "moderator"
    And I go to the homepage of the "Ring of Truth" collection
    Then I should see the link "Add document"

  Scenario: Add document as a facilitator.
    Given user:
      | Username    | napcheese         |
      | First name  | Eirik             |
      | Family name | Andries           |
      | E-mail      | eandr@example.com |
    And the following "collection" content:
      | title                | logo     |
      | Hunter in the Swords | logo.png |
    And the following collection user membership:
      | collection           | user      | roles       |
      | Hunter in the Swords | napcheese | facilitator |
    # Log in as a facilitator of the "Hunter in the Swords" collection.
    Given I am logged in as napcheese

    When I go to the homepage of the "Hunter in the Swords" collection
    And I click "Add document" in the plus button menu
    Then I should see the heading "Add document"
    And the following fields should be present "Title, Short title, Abstract, Body, Type, Topic, Search keywords, Geographical coverage, Licence, Documentation"
    # The entity is new, so the current workflow state should not be shown.
    And the following fields should not be present "Current workflow state, Motivation"
    And the following fields should not be present "Shared on"

    When I fill in the following:
      | Title       | An amazing document |
      | Short title | Amazing document    |
      | Abstract    | Amazing document    |
    And I select "Document" from "Type"
    And I press "Save as draft"
    Then I should see the following inline form error messages:
      | error messages                 |
      | Topic field is required.       |
      | This value should not be null. |

    When I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | This is going to be an amazing document. |
      | field_body:format | content_editor                           |
    And I attach the file "test.zip" to "files[resources_0_subform_resource_file_0]"
    And I select "EU and European Policies" options from "Topic"
    And I press "Save as draft"
    Then I should see the heading "An amazing document"
    And I should see the success message 'Document An amazing document has been created as draft. You can find it in the section "My unpublished content" located in your My account page, or in the aforementioned section under the Collection it was created in.'
    And Log entry with the message "A new document entity titled An amazing document has been created by Eirik Andries in Hunter in the Swords." should exist.
    And I should see the link "test.zip"
    # Check that the full author name is shown instead of the username.
    And I should see the link "Eirik Andries" in the "Content" region
    But I should not see the link "napcheese" in the "Content" region
    When I click "Edit"
    And I press "Edit"
    And I should see "Description"
    And I fill in "Description" with "My document"
    And I press "Save as draft"
    And I should not see "test.zip"
    And I should see "My document"

  # Regression test to ensure that no critical errors are thrown when a new
  # revision is created for a document that has a remote file attached.
  # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3670
  Scenario: Remote URLs can be used in documents.
    Given the following "collection" content:
      | title         |
      | Strong Lasers |
    And the following "licence" content:
      | title                 | description                                                         | licence type  |
      | Creative Commons Zero | CC0 is a legal tool for waiving as many rights as legally possible. | Public domain |
    And the following "document" content:
      | title       | document type | short title | resources              | abstract | field_paragraphs_body | licence               | collection    |
      | Laser types | document      | L-Types     | http://www.example.com | Abstract | List of laser types.  | Creative Commons Zero | Strong Lasers |

    When I am logged in as a facilitator of the "Strong Lasers" collection
    And I go to the edit form of the "Laser types" document
    And I press "Update"
    Then I should see the success message "Document Laser types has been updated"
    And I go to the edit form of the "Laser types" document
    And I press "Edit"
    And I should see "Link text"
    And I fill in "Link text" with "My document"
    And I press "Update"
    And I should not see "http://www.example.com "
    And I should see "My document"

  Scenario: Increase attachment file size limit in Documents.
    Given the following "collection" content:
      | title      |
      | Red blaser |
    And the following "licence" content:
      | title                 | description                                                         | licence type  |
      | Creative Commons Zero | CC0 is a legal tool for waiving as many rights as legally possible. | Public domain |
    And the following "document" content:
      | title            | document type | short title | abstract | field_paragraphs_body | licence               | collection |
      | Red blaser types | document      | RL-Types    | Abstract | List of laser types.  | Creative Commons Zero | Red blaser |

    When I am logged in as a facilitator of the "Red blaser" collection
    And I go to the edit form of the "Red blaser types" document
