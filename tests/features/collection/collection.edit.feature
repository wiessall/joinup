@api @group-a
Feature: Editing collections
  In order to manage collections
  As a collection owner or collection facilitator
  I need to be able to edit collections through the UI

  Scenario: Edit a collection
    Given the following "collection" content:
      | title                 | access url                             | closed | creation date    | content_creation         | group_moderation | state |
      | Überwaldean Land Eels | http://dogtime.com/dog-breeds/profiles | yes    | 28-01-1995 12:05 | facilitators and authors | yes              | draft |

    When I am logged in as a facilitator of the "Überwaldean Land Eels" collection
    And I go to the edit form of the "Überwaldean Land Eels" collection
    Then I should see the heading "Edit Collection Überwaldean Land Eels"
    Then the following fields should be present "Title, Description, Abstract, Topic, Geographical coverage, Search keywords, Motivation"
    And the following field widgets should be present "Contact information, Owner"
    And the following fields should not be present "Langcode, Translation, Affiliates, Enable the search field, Query presets, Limit"
    And I should see the text "Short description text of the collection. Appears on the Overview page."
    And I should see the text "Add a country name relevant to the content of this collection."
    And the "Description" wysiwyg editor should have the buttons "Heading,Style,Bold,Italic,Remove Format,Link,Contact us,Bulleted list,Numbered list,Outdent,Indent,Block quote,File,Drupal media,table,Undo,Redo,Source,Code,Code Block"

    # Query builder is disabled in collections.
    But I should not see the button "Add and configure filter"

    # Quick check to see that we can edit a field.
    When I fill in "Title" with "Überwaldean Sea Eels"
    And I press "Save as draft"
    Then I should see the heading "Überwaldean Sea Eels"

  @javascript
  Scenario: Edit a collection wysiwyg modal should close.
    # Regression test for ticket ISAICP-7181 and ISAICP-8355.
    Given the following "collection" content:
      | title           | access url                             | closed | creation date    | content_creation         | group_moderation | state |
      | Rhyme Land Eels | http://dogtime.com/dog-breeds/profiles | yes    | 28-01-1995 12:05 | facilitators and authors | yes              | draft |
    When I am logged in as a facilitator of the "Rhyme Land Eels" collection

    And I go to the edit form of the "Rhyme Land Eels" collection
    And I press the button "Insert Media" in the "Description" wysiwyg editor
    And a modal should open
    And I click "Remote video"
    When I fill in "Add Remote video via URL" with "https://www.youtube.com/watch?v=dQw4w9WgXcQ"
    And I press "Add"
    Then I press "Save" in the "Modal buttons" region
    And I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed
    And I press "Propose"

    Then I should see the heading "Rhyme Land Eels"
    And the page should contain the html text '<div class="media-video">'

  @javascript
  Scenario: Regression test wysiwyg add file shows a 0 bytes limit.
    # Regression test for ticket ISAICP-7177.
    Given the following "collection" content:
      | title                   | access url                             | closed | creation date    | content_creation         | group_moderation | state |
      | Rhyme Land wysiwyg Eels | http://dogtime.com/dog-breeds/profiles | yes    | 28-01-1995 12:05 | facilitators and authors | yes              | draft |
    When I am logged in as a facilitator of the "Rhyme Land wysiwyg Eels" collection

    And I go to the edit form of the "Rhyme Land wysiwyg Eels" collection
    And I press the button "Insert file" in the "Description" wysiwyg editor
    Then a modal should open
    And I should not see the text "0 bytes limit"
    When I attach the file "empty_pdf.pdf" to "File"
    And I press "Save" in the "Modal buttons" region
    Then the modal should be closed
