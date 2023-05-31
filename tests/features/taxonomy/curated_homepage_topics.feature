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
    Given I am an anonymous user
    And I go to the homepage
    # No homepage topic, no block title.
    Then I should not see the heading "Main topics"

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
    And I fill in "edit-items-add-more-new-item-target-id" with "E-justice"
    And I press "Add item"

    When I press "Save"
    Then I should see the error message "The E-justice topic is already in the list."

    When I fill in "edit-items-add-more-new-item-target-id" with "Finance in EU"
    And I press "Add item"
    # With Javascript disabled this field is filled with the latest value, so we
    # need to explicitly clear the value.
    And I fill in "edit-items-add-more-new-item-target-id" with ""
    And I press "Save"

    Then the "form table" table should contain a column with the following data:
      # The 'Edit' keyword is because there is an edit link in the table cell.
      | E-justice Edit     |
      | Finance in EU Edit |

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
    And the "form table" table should contain a column with the following data:
      | Finance in EU Edit |

    When I go to the homepage
    Then I should not see the text "E-justice" in the "Homepage topics" region
    But I should see the text "Finance in EU" in the "Homepage topics" region

  Scenario: Moderators can alter the title of the homepage topics block.
    Given I am logged in as a moderator
    When I click "Dashboard"
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

  Scenario: Configurable listings are available for the topic meta entities.
    Given the following topics are enabled in the homepage:
      | E-justice |
    And I am logged in as a moderator
    And I go to the "topic" meta entity of the "E-justice" topic "taxonomy term"
    And I click "Edit"
    Then I should see the heading "Edit E-justice"
    And I should not see the button "Add configurable listing"
    When I press "Add Row"
    Then I should not see the button "Add 2 column with featured media on the left"
    Then I should not see the button "Add 2 column with featured media on the right"
    Then I should not see the button "Add 3 columns"
    And I press "Add 1 column layout"
    Then I should see the button "Add Configurable listing"
    And I should not see the button "Add Accordion"
    And I should not see the button "Add Iframe"
    And I should not see the button "Add Carousel"
    And I should not see the button "Add Content listing"
    And I should not see the button "Add Image"
    And I should not see the button "Add Map"
    And I should not see the button "Add Testimony"

    Given I reload the page
    And I press "Add Row"
    And I press "Add 2 column layout"
    Then I should see the button "Add Configurable listing"

  Scenario: Lists of configurable content can be added in the page of the topic meta entity.
    Given news content:
      | title  | headline | state     |
      | News 1 | News 1   | validated |
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

    When I click "E-justice"
    And I click "Edit"
    When I press "Add Row"
    And I press "Add 2 column layout"
    And I press "Add Configurable listing"

    When I reload the page
    And I press "Add Row"
    And I press "Add 1 column layout"
    And I press "Add Configurable listing"
    Then the following fields should be present "Heading, Action link, List style, Query presets"

    When I fill in "Heading" with "E-justice heading"
    And I fill in "URL" with "http://example.com"
    And I fill in "Link text" with "E-justice link text"
    And I select "List" from "List style"
    And I fill in "Query presets" with "entity_bundle|news"

    And I press "Save"
    Then I should see the "News 1" tile
    And the url should match "/topic/law-and-justice/e-justice"

    When I click "Edit"
    And I select "Grid" from "List style"
    And I press "Save"
    And I should see the "News 1" tile
    And the url should match "/topic/law-and-justice/e-justice"

    When I go to the "topic" meta entity of the "Economy and Welfare" topic "taxonomy term"
    And the url should match "/topic/economy-and-welfare"

    When I go to the "topic" meta entity of the "Info" topic "taxonomy term"
    And the url should match "/topic/info"

    When I go to the "topic" meta entity of the "Statistics and Analysis" topic "taxonomy term"
    And the url should match "/topic/info/statistics-and-analysis"

    # Regression test: 3-dots is hidden for anonymous as only contains 'View'.
    Given I am an anonymous user
    And I visit "/topic/info/statistics-and-analysis"
    Then I should not see the button "Show primary options"

  Scenario: Editing from the administration page, redirect back to the administration page.
    When I am logged in as a moderator
    And I click "Dashboard"
    And I click "Topics"
    And I click "Edit" in the "E-justice" row
    Then I should see the heading "Edit E-justice"
    And I press "Save"
    Then the url should match "/admin/homepage-topics"

  @javascript
  Scenario: Long descriptions in topic inner page should be partially hidden.
    Given news content:
      | title  | headline | state     |
      | News 1 | News 1   | validated |
    And the following topics are enabled in the homepage:
      | Economy and Welfare     |
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

    # Open collapse
    When I press "See more"
    Then I should see the text "See less"
    And I should not see the text "See more"
    Then the "See less" collapse is expanded

    # Close collapse again
    When I press "See less"
    Then I should see the text "See more"
    And I should not see the text "See less"
    Then the "See more" collapse is collapsed

  Scenario: Regression test to ensure order of entities.
    Given news content:
      | title  | headline | body              | state     | created    | changed    | topic     |
      | News 1 | News 1   | Test              | validated | 01-01-2023 | 03-02-2023 | E-justice |
      | News 2 | News 2   | Test              | validated | 02-01-2023 | 02-02-2023 | E-justice |
      | News 3 | News 3   | Test              | validated | 03-01-2023 | 01-02-2023 | E-justice |
      | News 4 | News 4   | Should not appear | validated | 04-01-2023 | 01-01-2023 | E-health  |
    And event content:
      | title   | body | start date   | end date    | created    | state     | topic     |
      | Event 1 | Test | now -8 days  | now -9 days | now -4 day | validated | E-justice |
      | Event 2 | Test | now -9 days  | now -8 days | now -3 day | validated | E-justice |
      | Event 3 | Test | now -10 days | now -7 days | now -5 day | validated | E-justice |
    And the following topics are enabled in the homepage:
      | Economy and Welfare |
      | E-justice           |

    When I am logged in as a moderator
    And I go to the homepage
    And I click "E-justice" in the "Homepage topics" region
    And I click "Edit"
    When I press "Add Row"
    And I press "Add 1 column layout"
    And I press "Add Configurable listing"
    When I fill in "Heading" with "E-justice heading"
    And I fill in "URL" with "http://example.com"
    And I fill in "Link text" with "E-justice link text"
    And I select "List" from "List style"
    And I fill in "Query presets" with "entity_bundle|news"
    And I press "Save"
    Then I should see the following tiles in the correct order:
      | News 4 |
      | News 3 |
      | News 2 |
      | News 1 |

    When I click "Edit"

    # Test having 2 topics in the query preset with an 'IN' operator.
    And I fill in "Query presets" with:
      """
      entity_bundle|news
      topic|http://joinup.eu/ontology/topic#e-justice, http://joinup.eu/ontology/topic#e-health|IN
      """
    And I press "Save"
    Then I should see the following tiles in the correct order:
      | News 4 |
      | News 3 |
      | News 2 |
      | News 1 |

    When I click "Edit"
    And I fill in "Query presets" with:
      """
      entity_bundle|news
      topic|http://joinup.eu/ontology/topic#e-justice
      """
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

    When I click "Edit"
    And I fill in "Query presets" with "entity_bundle|event"
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

  Scenario Outline: Images should be shown for tile-two view modes.
    Given <type> content:
      | title       | headline    | short title | state   | logo       | published |
      | Custom tile | Custom tile | Custom tile | <state> | linus.jpeg | yes       |
    And the following topics are enabled in the homepage:
      | E-justice |
    And I am logged in as a moderator
    And I go to the homepage
    And I click "E-justice"
    And I click "Edit"
    When I press "Add Row"
    And I press "Add 1 column layout"
    And I press "Add Configurable listing"
    And I select "<list style>" from "List style"
    When I fill in "Heading" with "E-justice heading"
    And I fill in "URL" with "http://example.com"
    And I fill in "Link text" with "E-justice link text"
    And I fill in "Query presets" with "entity_bundle|<type>"
    And I press "Save"
    Then I <should> see the image "linus.jpeg" in the "Custom tile" tile

    Examples:
      | type        | should     | list style | state     |
      | custom_page | should     | list       | published |
      | custom_page | should     | grid       | published |
      | news        | should     | list       | validated |
      | news        | should     | grid       | validated |
      | event       | should     | list       | validated |
      | event       | should     | grid       | validated |
      | document    | should     | list       | validated |
      | document    | should     | grid       | validated |
      # Discussions don't have logos.
      | discussion  | should not | list       | validated |
      | discussion  | should not | grid       | validated |

  Scenario: A configurable listing with no results should not print its title.
    Given news content:
      | title  | headline | state     |
      | News 1 | News 1   | validated |
    And the following topics are enabled in the homepage:
      | E-justice               |
    And I am logged in as a moderator
    And I go to the homepage

    When I click "E-justice"
    And I click "Edit"
    And I press "Add Row"
    And I press "Add 1 column layout"
    And I press "Add Configurable listing"

    When I fill in "Heading" with "E-justice heading"
    And I select "List" from "List style"
    # Use a nonsense query preset to make sure we have 0 results
    And I fill in "Query presets" with "entity_bundle|Nebuchadnezzar"

    And I press "Save"
    And the url should match "/topic/law-and-justice/e-justice"
    Then I should not see the "News 1" tile
    And I should not see the heading "E-justice heading"

  Scenario: Ampersand character is not decoded properly under a topic page.
    Given custom_page content:
      | title                    | body                                          | state     |
      | Legislation & Technology | What do we mean by Legislation & Technology?  | validated |
    And the following topics are enabled in the homepage:
      | E-justice               |
    And I am logged in as a moderator
    And I go to the homepage
    When I click "E-justice"
    And I click "Edit"
    And I press "Add Row"
    And I press "Add 1 column layout"
    And I press "Add Configurable listing"
    And I select "Custom page" from "Available filters"
    And I press "Add and configure filter"
    And I fill in "Custom page" with "Legislation & Technology"
    When I fill in "Heading" with "E-justice heading"
    And I select "Grid" from "List style"
    And I press "Save"
    And the url should match "/topic/law-and-justice/e-justice"
    And I should see the text "What do we mean by Legislation & Technology?" in the "Legislation & Technology" tile
    And I should not see the text "What do we mean by Legislation &amp; Technology?" in the "Legislation & Technology" tile
