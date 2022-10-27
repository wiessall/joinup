@api @group-d
Feature: Editing collections
  In order to manage collections
  As a collection owner or collection facilitator
  I need to be able to edit collections through the UI

  @terms
  Scenario: Edit a collection
    Given the following owner:
      | name                 | type                    |
      | Organisation example | Non-Profit Organisation |
    And the following contact:
      | name  | Collection editorial             |
      | email | collection.editorial@example.com |
    And collections:
      | title                 | logo     | banner     | abstract                                   | access url                             | closed | creation date    | description                               | content creation         | moderation | topic             | owner                | contact information  | state |
      | Überwaldean Land Eels | logo.png | banner.jpg | Read up on all about <strong>dogs</strong> | http://dogtime.com/dog-breeds/profiles | yes    | 28-01-1995 12:05 | The Afghan Hound is elegance personified. | facilitators and authors | yes        | Supplier exchange | Organisation example | Collection editorial | draft |
    When I am logged in as a facilitator of the "Überwaldean Land Eels" collection
    And I go to the homepage of the "Überwaldean Land Eels" collection
    And I click "Edit" in the "Entity actions" region
    Then I should see the heading "Edit Collection Überwaldean Land Eels"
    Then the following fields should be present "Title, Description, Abstract, Topic, Geographical coverage, Keywords, Content creation, Moderated, Motivation"
    And the following field widgets should be present "Contact information, Owner"
    And the following fields should not be present "Langcode, Translation, Affiliates, Enable the search field, Query presets, Limit"
    And I should see "Short description text of the collection. Appears on the Overview page. (Leave blank to use the trimmed value of the Description field.)"
    And I should see "Add a country name relevant to the content of this collection."
    And the "Description" wysiwyg editor should have the buttons "Heading,Bold,Italic,Remove Format,Link,Bulleted list,Numbered list,Outdent,Indent,Block quote,Image,table,Drupal media,Undo,Redo,Source,Code,Code Block"
    And the "Abstract" wysiwyg editor should have the buttons "Heading,Bold,Italic,Remove Format,Link,Outdent,Indent,Source,Code,Code Block"

    # Query builder is disabled in collections.
    But I should not see the button "Add and configure filter"

    # Quick check to see that we can edit a field.
    When I fill in "Title" with "Überwaldean Sea Eels"
    And I press "Save as draft"
    Then I should see the heading "Überwaldean Sea Eels"


  @terms @javascript
  Scenario: Edit a collection wysiwyg modal should close.
    # Regression test for ticket ISAICP-7181.
    Given the following owner:
      | name            | type                    |
      | wysiwyg example | Non-Profit Organisation |
    And the following contact:
      | name  | wysiwyg editorial             |
      | email | wysiwyg.editorial@example.com |
    And collections:
      | title           | logo     | banner     | abstract                                   | access url                             | closed | creation date    | description                    | content creation         | moderation | topic             | owner           | contact information  | state |
      | Rhyme Land Eels | logo.png | banner.jpg | Read up on all about <strong>dogs</strong> | http://dogtime.com/dog-breeds/profiles | yes    | 28-01-1995 12:05 | Hound is elegance personified. | facilitators and authors | yes        | Supplier exchange | wysiwyg example | wysiwyg editorial    | draft |
    When I am logged in as a facilitator of the "Rhyme Land Eels" collection
    And I go to the homepage of the "Rhyme Land Eels" collection

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I press the button "Insert Drupal Media" in the "Description" wysiwyg editor
    Then I wait for AJAX to finish
    And a modal should open
    And I click "Videos"
    When I fill in "Add Videos URL" with "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
    And I press "Add"
    Then I press "Save" in the "Modal buttons" region
    And I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed

  @terms @javascript
  Scenario: Regression test wysiwyg add file shows a 0 bytes limit.
    # Regression test for ticket ISAICP-7177.
    Given the following owner:
      | name                       | type                    |
      | Regression wysiwyg example | Non-Profit Organisation |
    And the following contact:
      | name  | bytes editorial             |
      | email | bytes.editorial@example.com |
    And collections:
      | title           | logo     | banner     | abstract                                   | access url                             | closed | creation date    | description                    | content creation         | moderation | topic             | owner                      | contact information  | state |
      | Rhyme Land wysiwyg Eels | logo.png | banner.jpg | Read up on all about <strong>dogs</strong> | http://dogtime.com/dog-breeds/profiles | yes    | 28-01-1995 12:05 | Hound is elegance personified. | facilitators and authors | yes        | Supplier exchange | Regression wysiwyg example | bytes editorial      | draft |
    When I am logged in as a facilitator of the "Rhyme Land wysiwyg Eels" collection
    And I go to the homepage of the "Rhyme Land wysiwyg Eels" collection

    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    And I press the button "Insert Drupal Media" in the "Description" wysiwyg editor
    Then I wait for AJAX to finish
    And a modal should open
    And I click "Files"
    And I should not see the text "0 bytes limit"
    When I attach the file "empty_pdf.pdf" to "Add file"
    Then I press "Save" in the "Modal buttons" region
    And I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed
