@api @group-c
Feature: Add community content
  In order to introduce my wisdom in my collections
  As a member of a collection
  I need to be able to add community content

  Scenario Outline: Advanced content and group administration should not be accessible for group members
    Given the following collection:
      | title | The night shift |
      | state | published       |

    When I am logged in as a "<member type>" of the "The night shift" collection
    And I go to the homepage of the "The night shift" collection
    And I click "Add <content type>"
    Then I should see the heading "Add <content type>"
    But I should not see the following lines of text:
      | Authored by                  |
      | Authored on                  |
      | Create new revision          |
      | Generate automatic URL alias |
      | Groups audience              |
      | Other groups                 |
      | Promoted to front page       |
      | Revision information         |
      | Revision log message         |
      | Sticky at top of lists       |

    Examples:
      | member type | content type |
      | facilitator | discussion   |
      | facilitator | document     |
      | facilitator | event        |
      | facilitator | news         |
      | member      | discussion   |
      | member      | document     |
      | member      | event        |
      | member      | news         |

  Scenario Outline: Advanced content and group administration should not be accessible for moderators
    Given the following collection:
      | title | The night shift |
      | state | published       |

    When I am logged in as a "moderator"
    And I go to the homepage of the "The night shift" collection
    And I click "Add <content type>"
    Then I should see the heading "Add <content type>"
    And the following fields <author field present> be present "Authored by"
    Then I should see the text "Allowed types: azw css csv dbf doc docx dtd.sgml dtd.xml e00 eps epub fmx2 fmx3 fmx4 gif gml gzip indd jpeg jpg json jsonld kml kmz mdb mobi mop mxd odf ods odt op.datpro owl pdf pdfa1a pdfa1b pdfx png pps ppsx ppt pptx ps psd rdf rdf.turtle rdf.xml rss rtf schema.xml sgml skos.xml sparqlq sparqlqres tar tar.gz tar.xz tiff tsv ttl txt xls xlsx xml xslfo xslt zip."
    But I should not see the following lines of text:
      | Authored on                  |
      | Create new revision          |
      | Generate automatic URL alias |
      | Groups audience              |
      | Other groups                 |
      | Promoted to front page       |
      | Revision information         |
      | Revision log message         |
      | Sticky at top of lists       |

    Examples:
      | content type | author field present |
      | custom page  | should not           |
      | discussion   | should               |
      | document     | should               |
      | event        | should               |
      | news         | should               |

  Scenario Outline: Publishing community content for the first time sets the publication date
    Given users:
      | Username  | E-mail                     | First name | Family name    | Roles     |
      | Publisher | publisher-example@test.com | Publisher  | Georgakopoulos | moderator |
    And the following collection:
      | title | The afternoon shift |
      | state | published           |
    And discussion content:
      | title             | content         | author    | state | collection          | created    |
      | Sample discussion | Sample content. | Publisher | draft | The afternoon shift | 01-01-2010 |
    And event content:
      | title        | abstract | field_paragraphs_body | location          | author    | collection          | state | created    |
      | Sample event | abstract | Sample content.       | Buckingham Palace | Publisher | The afternoon shift | draft | 01-01-2010 |
    And news content:
      | title       | headline    | abstract | field_paragraphs_body | state | author    | collection          | created    |
      | Sample news | Sample news | abstract | Sample content.       | draft | Publisher | The afternoon shift | 01-01-2010 |

    When I am logged in as "Publisher"
    And I go to the "Sample <content type>" <content type>
    Then the "Sample <content type>" <content type> should not have a publication date
    And I should see the text "Published on: 01/01/2010"
    And I click "Edit" in the "Entity actions" region
    And I press "Publish"
    Then I should see the heading "Sample <content type>"
    And the publication date of the "Sample <content type>" <content type> should not be equal to the created date

    When I click "Revisions" in the "Entity actions" region
    And I click the last "Revert" link
    And I press "Revert"
    Then the publication date of the "Sample <content type>" <content type> should be equal to the last unpublished version's

    When I go to the "Sample <content type>" <content type>
    And I click "Edit" in the "Entity actions" region
    And I press "Publish"
    Then I should see the heading "Sample <content type>"
    And the publication date of the "Sample <content type>" <content type> should be equal to the last published version's

    # The document is not tested as the creation date is not shown in the page. For documents, the document publication
    # date is the one shown and this field is exposed to the user.
    Examples:
      | content type |
      | discussion   |
      | event        |
      | news         |

  Scenario: Directly publishing community content sets the correct publication date.
    Given the following collections:
      | title        | description                  | logo     | state     |
      | CC container | Community content container. | logo.png | published |
    And I am logged in as a "facilitator" of the "CC container" collection

    # Create a published discussion.
    When I go to the homepage of the "CC container" collection
    And I click "Add discussion" in the plus button menu
    And I fill in "Title" with "Published community discussion"
    When I enter "Published community discussion" in the "Content" wysiwyg editor
    And I select "Supplier exchange" from "Topic"
    And I press "Publish"
    Then I should see the heading "Published community discussion"
    And the publication date of the "Published community discussion" discussion should be equal to the created date

    # Create a published document.
    When I go to the homepage of the "CC container" collection
    And I click "Add document" in the plus button menu
    And I fill in the following:
      | Title       | Published community document |
      | Short title | Published community document |
      | Abstract    | Document abstract            |
    And I select "Document" from "Type"
    And I select "Supplier exchange" from "Topic"
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | Published community document body |
      | field_body:format | content_editor                    |
    And I press "Publish"
    Then I should see the heading "Published community document"
    And the publication date of the "Published community document" document should be equal to the created date

    # Create a published event.
    When I go to the homepage of the "CC container" collection
    And I click "Add event" in the plus button menu
    Then the following field should not be present "Summary"
    And I fill in the following:
      | Title       | Published community event |
      | Short title | Published community event |
      | Abstract    | Published community event |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | Published community event |
      | field_body:format | content_editor                    |
    And I select "Supplier exchange" from "Topic"
    And I press "Add another item" at the "Virtual location" field
    And I fill the start date of the Date widget with "2018-08-29"
    And I fill the start time of the Date widget with "23:59:59"
    And I fill the end date of the Date widget with "2018-08-30"
    And I fill the end time of the Date widget with "12:57:00"
    And I fill in "Physical location" with "Rue Belliard 28, Brussels, Belgium"
    And I press "Publish"
    Then I should see the heading "Published community event"
    # We are not testing events as behat assigns a slightly different publication date than the creation date.
    # e.g. if the creation date is 1147483647, the publication date assigned will be 1147483645.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-5679
    # And the publication date of the "Published community event" event should be equal to the created date

    # Create a published news.
    When I go to the homepage of the "CC container" collection
    And I click "Add news" in the plus button menu
    Then the following field should not be present "Summary"
    And I fill in the following:
      | Short title | Published community news |
      | Abstract    | Abstract                 |
      | Headline    | Published community news |
    And I append text to field_paragraphs_body layout paragraphs field
      | field_body:value  | Published community news |
      | field_body:format | content_editor           |
    And I select "Supplier exchange" from "Topic"
    And I press "Publish"
    Then I should see the heading "Published community news"
    And the publication date of the "Published community news" news should be equal to the created date

  Scenario Outline: Community content type is indicated on the byline
    Given the following collection:
      | title | The night watch |
      | state | published       |
    And <content type> content:
      | title                   | collection      | state     |
      | The glorious revolution | The night watch | published |
    When I go to the content page of the type "<content type>" with the title "The glorious revolution"
    Then I should see the text <label> in the "Page title" region

    Examples:
      | content type | label      |
      | discussion   | Discussion |
      | document     | Document   |
      | news         | News       |

  Scenario: Event content type is not indicated on the byline
    Given the following collection:
      | title | The night batch |
      | state | published       |
    And event content:
      | title                    | collection      | state     | start date   | end date            | state     |
      | Red glorious revolution  | The night batch | published | now +1 years | now +1 years +1 day | published |
      | Blue glorious revolution | The night batch | published | now -1 years | now -1 years +1 day | published |
    When I go to the content page of the type "event" with the title "Red glorious revolution"
    And I should see the button "Add to Calendar"
    And I should see the link "Google"
    And I should see the link "Yahoo!"
    And I should see the link "Outlook.com"
    And I should see the link "Office.com"
    And I should see the link "iCal / MS Outlook"

    When I go to the content page of the type "event" with the title "Blue glorious revolution"
    And I should not see the button "Add to Calendar"
    And I should not see the link "Google"
    And I should not see the link "Yahoo!"
    And I should not see the link "Outlook.com"
    And I should not see the link "Office.com"
    And I should not see the link "iCal / MS Outlook"

  @javascript @loggedErrors @api @uploadFiles:logo.png
  Scenario: Editor File dialog form does not trigger warning
    Given the following collection:
      | title | The editor tester collection |
      | state | published                    |
    And the site error reporting verbosity is "verbose"

    When I am logged in as a "facilitator" of the "The editor tester collection" collection
    And I go to the "The editor tester collection" collection
    And I click "Add discussion" in the plus button menu
    Then I should see the heading "Add discussion"
    And I should see the "Content" wysiwyg editor

    When there are no preexisting log records on the site
    And the "Insert Media" button should be available in the "Content" wysiwyg editor
    Then I press the button "Insert Media" in the "Content" wysiwyg editor
    And I attach the file "logo.png" to "Add file"
    And I wait for AJAX to finish
    When I fill in "Alternative text" with "Example logo"
    And I press "Save" in the "Modal buttons" region
    And I wait for AJAX to finish

    When I press "Insert selected" in the "Modal buttons" region
    Then the modal should be closed
    And the site should not have log records with level lower than "notice"

