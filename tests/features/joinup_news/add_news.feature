@api @group-f
Feature: Creation of news through the UI.
  In order to manage news
  As a user
  I need to be able to create news through the UI.

  @uploadFiles:logo.png,test.zip
  Scenario: Share the news in other collections/solutions.
    Given user:
      | Username    | isotopedancer      |
      | First name  | Milana             |
      | Family name | Laninga            |
      | E-mail      | milana@example.com |
    And the following collections:
      | title            | description                                 | logo     | state     |
      | Metal fans       | "Share the love for nickel, tungsten & co." | logo.png | published |
      | Hardcore diggers | We dig up stuff hidden beneath the earth.   | logo.png | published |
      | Cool blacksmiths | Keeping it cool while working on hot stuff. | logo.png | published |
    And solutions:
      | title                     | description                               | logo     | state     |
      | Density catalogue project | Catalog density on metals with ease.      | logo.png | published |
      | Dig do's and don'ts       | How to dig up stuff with style.           | logo.png | published |
      | Anvil test routines       | How to determine reliability of the tool. | logo.png | published |
    And the following solution user membership:
      | solution                  | user          | roles       |
      | Density catalogue project | isotopedancer | facilitator |

    When I am logged in as a "facilitator" of the "Metal fans" collection
    And I go to the homepage of the "Metal fans" collection
    Then the following fields should not be present "Shared on, Motivation"

    # Log in as a facilitator of the "Density catalogue project" solution
    When I am logged in as isotopedancer
    And I go to the homepage of the "Density catalogue project" solution
    And I click "Add news" in the plus button menu

    # Check required fields.
    And I attach the file "test.zip" to "Add a new file"
    And I attach the file "logo.png" to "Logo"
    And I press "Upload"
    And I press "Publish"
    Then I should see the following lines of text:
      | Headline field is required.                    |
      | Short title field is required.                 |
      | Abstract field is required.                    |
      | The Attachments field description is required. |
      | This value should not be null.                 |

    When I fill in the following:
      | Short title      | Ytterbium has won the ultimate heavy metal of the year award of 2020!                         |
      | Headline         | Strong request for this rare metal that is on the mouth of everybody                          |
      | Abstract         | Abstract of Strong request for... news                                                        |
      | File description | Comparison materials                                                                          |
    And I append "text" to "field_paragraphs_body" layout paragraphs field
      | field_body:value  | Thanks to its lower density compared to thulium and lutetium its applications have increased. |
      | field_body:format | content_editor                                                                                |

    # Reference a solution in the news.
    When I fill in "Referenced solution" with "Dig do's and don'ts"
    # Test that the title character limit is restricted. This cannot be reproduced in the normal UI but checks the form
    # validation.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3680
    And I press "Publish"
    Then I should see the following error messages:
      | error messages                                                                       |
      | Short title cannot be longer than 66 characters but is currently 69 characters long. |
      | Topic field is required.                                                             |

    When I fill in "Short title" with "Ytterbium metal of the year"
    And I select "EU and European Policies" from "Topic"
    And I press "Publish"
    Then I should see the success message "News Ytterbium metal of the year has been created."
    And Log entry with the message "A new news entity titled Ytterbium metal of the year has been created by Milana Laninga in Density catalogue project." should exist.
    # Verify that the author is visible.
    And I should see the text "Milana Laninga"
    # Verify that the referenced solution is rendered as tile.
    And I should see the "Dig do's and don'ts" tile
    # Check that the full author name is shown instead of the username.
    And I should see the link "Milana Laninga" in the "Page title" region
    But I should not see the link "isotopedancer" in the "Page title" region

    # Edit again and try to share onto the same solution.
    When I click "Edit" in the "Entity actions" region
    And I fill in "Referenced solution" with values "Dig do's and don'ts, Dig do's and don'ts"
    And I press "Update"
    Then I should see the error message "The value Dig do's and don'ts is already selected for field Referenced solution."

    # Add another solution in the field.
    When I fill in "Referenced solution" with values "Dig do's and don'ts, Anvil test routines"
    And I press "Update"
    Then I should see the success message "News Ytterbium metal of the year has been updated."
    # Verify that the tiles are shown.
    Then I should see the "Dig do's and don'ts" tile
    And I should see the "Anvil test routines" tile

    When I visit the content overview
    Then I should see the image "logo.png" in the "Ytterbium metal of the year" tile

  @javascript @generateMedia @generateMediaTypes:news_logo @uploadFiles:logo.png
  Scenario: Test the image library widget.
    Given the following collection:
      | title | Stream of Dreams |
      | state | published        |
    And news content:
      | title             | collection       | headline      | abstract           | field_paragraphs_body | state     |
      | The Great Opening | Stream of Dreams | Here we go... | Image library test | It opens!             | published |

    Given I am logged in as a moderator

    # Upload works.
    When I go to the edit form of the "The Great Opening" news
    And I attach the file "logo.png" to "Logo"

    # Picking-up pre-uploaded images works.
    When I remove the file from "Logo"
    And I wait for AJAX to finish

    And I select image #6 as news logo
    And I wait for AJAX to finish
    And I press "Update"
    And the "The Great Opening" news logo is image #6
