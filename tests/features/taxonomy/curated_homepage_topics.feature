@api @topic @group-i
Feature:
  Ensure curated list of topics on the homepage behave properly.

  Scenario: Show list of topics meta entity type and check a given parent and published field.
    Given I am logged in as a moderator
    When I go to "/dashboard"
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
    And I go to "/dashboard"
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
    And I should see the following inline form error messages:
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
    And I fill in "edit-items-add-more-new-item-target-id" with "E-justice"
    And I press "Add item"

    When I press "Save"
    Then I should see the inline form error message "The E-justice topic is already in the list."

    When I fill in "edit-items-add-more-new-item-target-id" with "Finance in EU"
    And I press "Add item"
    # With Javascript disabled this field is filled with the latest value, so we
    # need to explicitly clear the value.
    And I fill in "edit-items-add-more-new-item-target-id" with ""
    And I press "Save"

    When I go to the homepage
    Then I should see the text "E-justice" in the "Homepage topics" region
    And I should see the text "E-justice description" in the "Homepage topics" region
    And I should see the text "Finance in EU" in the "Homepage topics" region
    And I should see the text "Finance in EU description" in the "Homepage topics" region
    # The block title is shown now.
    And I should see the heading "Main topics"

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

    When I go to the homepage
    Then I should not see the text "E-justice" in the "Homepage topics" region
    But I should see the text "Finance in EU" in the "Homepage topics" region

  Scenario: Moderators can alter the title of the homepage topics block.
    Given I am logged in as a moderator
    When I go to "/dashboard"
    And I click "Topics"
    And I click "Edit" in the "E-justice" row
    And I check "Published"
    And I press "Save"

    When I visit "/admin/structure/entityqueue/homepage_topics/homepage_topics"
    Then I should see the heading "Update curated content listing Homepage topics"
    When I fill in "edit-items-add-more-new-item-target-id" with "E-justice"
    And I press "Add item"
    # With Javascript disabled this field is filled with the latest value, so we
    # need to explicitly clear the value.
    And I fill in "edit-items-add-more-new-item-target-id" with ""
    And I press "Save"
    And I should see the button "Add item"
    And I fill in "Block label" with "Hello world"
    And I press "Save"

    When I go to the homepage
    Then I should see the heading "Hello world"
    And I should not see the heading "Main topics"

    # Unset the value and check again.
    When I am logged in as a moderator
    And I click "Dashboard"
    And I click "Curated content listings"
    And I click "Homepage topics"
    Then I should see the heading "Update curated content listing Homepage topics"
    And I should see the button "Add item"

    When I fill in "Block label" with ""
    And I press "Save"

    When I go to the homepage
    Then I should see the heading "Main topics"
    And I should not see the heading "Hello world"

  Scenario: Description and summary are displayed accordingly.
    Given news content:
      | title  | headline |
      | News 1 | News 1   |
    And the following topics are enabled in the homepage:
      | Economy and Welfare     |
      | Info                    |
      | Statistics and Analysis |
      | E-justice               |
    And I am logged in as a moderator
    And I go to the homepage
    And I click "E-justice"
    And I click "Edit"
    Then I should see the heading "Edit E-justice"

    When I fill in "Description" with "This now has to be shown both in the inner page and in the homepage."
    And I press "Save"
    Then I should see the heading "E-justice"
    And I should see the text "This now has to be shown both in the inner page and in the homepage."

    When I go to the homepage
    Then I should see the text "This now has to be shown both in the inner page and in the homepage."

    When I click "E-justice"
    And I click "Edit"
    And I fill in "Summary" with "This will be shown in the homepage."
    And I fill in "Description" with "This will be shown in the inner page."
    And I press "Save"
    Then I should see the text "This will be shown in the inner page."

    When I go to the homepage
    Then I should see the text "This will be shown in the homepage."

  @javascript
  Scenario: Lists of configurable content can be added in the page of the topic meta entity.
    Given news content:
      | title  | headline | collection |
      | News 1 | News 1   | Portal support |
    And custom_page content:
      | title          | collection |
      | Popular quotes | Portal support |
    And I append "text" to "field_paragraphs_body" field in "node" entity with following "Popular Quotes" title:
      | field_body:value  |     That's one small step for a&nbsp;man, a giant leap for mankind. |
      | field_body:format | text_html                                                           |
    And the following topics are enabled in the homepage:
      | Economy and Welfare     |
      | Info                    |
      | Statistics and Analysis |
      | E-justice               |
    When I am logged in as a moderator
    And I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    And I add a layout paragraph in the "Topic meta entity body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                                       |
      | region          | content                                                 |
      | Heading         | type: text - value: E-justice heading                   |
      | Action link     | type: text - value: http://example.com                  |
      | Link text       | type: text - value: E-justice link text                 |
      | List style      | type: select - value: List                              |
      | Query presets   | type: text - value: entity_bundle\|news,custom_page\|IN |

    And I press "Save"
    Then I should see the "News 1" tile
    And I should see the "Popular quotes" tile
    # Regression test that text is trimmed properly.
    Then the response should contain "default abstract"
    And the url should match "/topic/law-and-justice/e-justice"

    When I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    And I "delete" from the "Topic meta entity body" field the child 1 paragraph of 1 layout
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                       |
      | region          | content                                 |
      | Heading         | type: text - value: E-justice heading   |
      | Action link     | type: text - value: http://example.com  |
      | Link text       | type: text - value: E-justice link text |
      | List style      | type: select - value: Grid              |
      | Query presets   | type: text - value: entity_bundle\|news |

    And I press "Save"
    And I should see the "News 1" tile
    And the url should match "/topic/law-and-justice/e-justice"

    When I go to the canonical path of the "topic" meta entity of the "Economy and Welfare" topic "taxonomy term"
    And the url should match "/topic/economy-and-welfare"

    When I go to the canonical path of the "topic" meta entity of the "Info" topic "taxonomy term"
    And the url should match "/topic/info"

    When I go to the canonical path of the "topic" meta entity of the "Statistics and Analysis" topic "taxonomy term"
    And the url should match "/topic/info/statistics-and-analysis"

    # Regression test: 3-dots is hidden for anonymous as only contains 'View'.
    Given I am an anonymous user
    And I visit "/topic/info/statistics-and-analysis"
    Then I should not see the button "Show primary options"

    # Editing from the administration page, redirect back to the administration page.
    When I am logged in as a moderator
    And I go to "/dashboard"
    And I click "Topics"
    And I click "Edit" in the "E-justice" row
    Then I should see the heading "Edit E-justice"
    And I press "Save"
    Then the url should match "/admin/homepage-topics"

  @javascript
  Scenario: Long descriptions in topic inner page should be partially hidden.
    Given the following topics are enabled in the homepage:
      | Info                    |
      | Statistics and Analysis |
      | E-justice               |
    And I am logged in as a moderator
    And I go to the homepage
    And I click "E-justice"
    And I open the header local tasks menu
    And I click "Edit"
    Then I should see the heading "Edit E-justice"

    When I enter "En un lugar de la Mancha, de cuyo nombre no quiero acordarme, no ha mucho tiempo que vivía un hidalgo de los de lanza en astillero, adarga antigua, rocín flaco y galgo corredor. Una olla de algo más vaca que carnero, salpicón las más noches, duelos y quebrantos los sábados, lantejas los viernes, algún palomino de añadidura los domingos, consumían las tres partes de su hacienda. El resto della concluían sayo de velarte, calzas de velludo para las fiestas, con sus pantuflos de lo mesmo, y los días de entresemana se honraba con su vellorí de lo más fino." in the "Description" wysiwyg editor
    And I press "Save"
    Then I should see the heading "E-justice"
    Then the "See more" collapse is collapsed
    And I should see the text "See more"

    # Open collapse.
    When I press "See more"
    Then I wait for CSS animations to finish
    And I should not see the text "See more"
    Then the "See less" collapse is expanded

    # Close collapse again.
    When I press "See less"
    Then I wait for CSS animations to finish
    And I should not see the text "See less"
    Then the "See more" collapse is collapsed

  @javascript
  Scenario: Regression test to ensure order of entities.
    Given news content:
      | title  | headline | body              | created    | changed    | topic     |
      | News 1 | News 1   | Test              | 01-01-2023 | 03-02-2023 | E-justice |
      | News 2 | News 2   | Test              | 02-01-2023 | 02-02-2023 | E-justice |
      | News 3 | News 3   | Test              | 03-01-2023 | 01-02-2023 | E-justice |
      | News 4 | News 4   | Should not appear | 04-01-2023 | 01-01-2023 | E-health  |
    And event content:
      | title   | field_paragraphs_body | start date   | end date    | created    | topic     |
      | Event 1 | Test                  | now -8 days  | now -9 days | now -4 day | E-justice |
      | Event 2 | Test                  | now -9 days  | now -8 days | now -3 day | E-justice |
      | Event 3 | Test                  | now -10 days | now -7 days | now -5 day | E-justice |
    And the following topics are enabled in the homepage:
      | Economy and Welfare |
      | E-justice           |
    When I am logged in as a moderator

    And I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    And I add a layout paragraph in the "Topic meta entity body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                       |
      | region          | content                                 |
      | Heading         | type: text - value: E-justice heading   |
      | Action link     | type: text - value: http://example.com  |
      | Link text       | type: text - value: E-justice link text |
      | List style      | type: select - value: List              |
      | Query presets   | type: text - value: entity_bundle\|news |
    And I press "Save"
    Then I should see the following tiles in the correct order:
      | News 4 |
      | News 3 |
      | News 2 |
      | News 1 |

    When I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    When I delete from the "Topic meta entity body" form field the layout paragraph in position 1
    And I add a layout paragraph in the "Topic meta entity body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                                                                                                                       |
      | region          | content                                                                                                                                 |
      | Heading         | type: text - value: E-justice heading                                                                                                   |
      | Action link     | type: text - value: http://example.com                                                                                                  |
      | Link text       | type: text - value: E-justice link text                                                                                                 |
      | List style      | type: select - value: List                                                                                                              |
      | Query presets   | type: text - value: entity_bundle\|news\ntopic\|http://joinup.eu/ontology/topic#e-justice, http://joinup.eu/ontology/topic#e-health\|IN |
    And I press "Save"
    Then I should see the following tiles in the correct order:
      | News 4 |
      | News 3 |
      | News 2 |
      | News 1 |

    When I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    When I delete from the "Topic meta entity body" form field the layout paragraph in position 1
    And I add a layout paragraph in the "Topic meta entity body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                                         |
      | region          | content                                                   |
      | Heading         | type: text - value: E-justice heading                     |
      | Action link     | type: text - value: http://example.com                    |
      | Link text       | type: text - value: E-justice link text                   |
      | List style      | type: select - value: List                                |
      | Query presets   | type: text - value: entity_bundle\|news\ntopic\|e-justice |
    And I press "Save"
    Then I should see the following tiles in the correct order:
      | News 3 |
      | News 2 |
      | News 1 |

    When I go to the edit form of the "News 2" news
    And I press "Save"
    And I go to the homepage
    And I click "E-justice" in the "Homepage topics" region
    # The change should not affect the order as the news publication date is the same.
    Then I should see the following tiles in the correct order:
      | News 3 |
      | News 2 |
      | News 1 |

    When I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    When I delete from the "Topic meta entity body" form field the layout paragraph in position 1
    And I add a layout paragraph in the "Topic meta entity body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                        |
      | region          | content                                  |
      | Heading         | type: text - value: E-justice heading    |
      | Action link     | type: text - value: http://example.com   |
      | Link text       | type: text - value: E-justice link text  |
      | List style      | type: select - value: List               |
      | Query presets   | type: text - value: entity_bundle\|event |
    And I press "Save"

    # Events are shown by the event date sorted by the most recent first.
    Then I should see the following tiles in the correct order:
      | Event 1 |
      | Event 2 |
      | Event 3 |

    When I go to the edit form of the "Event 3" event
    And I press "Save"
    And I go to the homepage
    # The change should not affect the order as the event start date is the same.
    And I click "E-justice" in the "Homepage topics" region
    Then I should see the following tiles in the correct order:
      | Event 1 |
      | Event 2 |
      | Event 3 |

  @javascript
  Scenario Outline: Images should be shown for tile-two view modes.
    Given <type> content:
      | title       | headline    | short title | logo       |
      | Custom tile | Custom tile | Custom tile | linus.jpeg |
    And the following topics are enabled in the homepage:
      | E-justice |
    And I am logged in as a moderator
    And I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    And I add a layout paragraph in the "Topic meta entity body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                         |
      | region          | content                                   |
      | Heading         | type: text - value: E-justice heading     |
      | Action link     | type: text - value: http://example.com    |
      | Link text       | type: text - value: E-justice link text   |
      | List style      | type: select - value: <list style>        |
      | Query presets   | type: text - value: entity_bundle\|<type> |
    And I press "Save"
    # \Drupal\Driver\Fields\Drupal8\ImageHandler changes the file name to a
    # random string, so the only thing we can check is the presence of any
    # image.
    Then there should be "<image count>" image in the "Custom tile" tile

    Examples:
      | type        | image count | list style |
      | custom_page | 1           | list       |
      | custom_page | 1           | grid       |
      | news        | 1           | list       |
      | news        | 1           | grid       |
      | event       | 1           | list       |
      | event       | 1           | grid       |
      | document    | 1           | list       |
      | document    | 1           | grid       |
      | discussion  | 1           | list       |
      | discussion  | 1           | grid       |

  @javascript
  Scenario: Configurable listing for solutions
    Given solution content:
      | title                   | description              | collection | abstract                          | topic                                                                    | created    | changed    |
      | Another rough day       | Lorem description issum  | Portal support |                                   | E-justice, Finance in EU, Supplier exchange, HR, Statistics and Analysis | 01-01-2023 | 03-02-2023 |
      | Just another day & solt | Second description issum | Portal support | This is an solution with abstract | E-justice                                                                | 02-01-2023 | 03-02-2023 |
    When I am logged in as a moderator
    And I go to the edit form of the topic meta entity of the "E-justice" topic "taxonomy term"
    Then I add a layout paragraph in the "Topic meta entity body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    And I add a "Configurable listing" paragraph in the "Topic meta entity body" form field:
      | layout position | 1                                                          |
      | region          | content                                                    |
      | Heading         | type: text - value: E-justice heading                      |
      | Action link     | type: text - value: http://example.com                     |
      | Link text       | type: text - value: E-justice link text                    |
      | List style      | type: select - value: List                                 |
      | Show content    | type: select - value: All                                  |
      | Query presets   | type: text - value: entity_bundle\|solution,collection\|IN |
    And I press "Save"
    Then the url should match "/topic/law-and-justice/e-justice"

    #  Ampersand character is not decoded properly under a topic page.
    And I should see the text "Just another day & solt" in the "Just another day & solt" tile
    And I should not see the text "Just another day amp solt" in the "Just another day & solt" tile

    # Show abstract instead of description in Collection/Solution tiles.
    And I should see the text "Portal support is a collaborative platform created by the European Commission and funded by the European Union via the ISA² Programme." in the "Portal support" tile
    And I should see the text "Lorem description issum" in the "Another rough day" tile
    And I should see the text "This is an solution with abstract" in the "Just another day & solt" tile

    # Up to 4 topics should be shown for tile-two view modes.
    # The change should not affect the order as the news publication date is the same.
    And I should see the text "E-justice" in the "Another rough day" tile
    And I should see the text "Finance in EU" in the "Another rough day" tile
    But I should not see the text "Supplier exchange" in the "Another rough day" tile
    And I should not see the text "HR" in the "Another rough day" tile
    And I should see the text "+3 topics" in the "Another rough day" tile
