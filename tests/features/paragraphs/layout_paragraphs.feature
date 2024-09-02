@api @javascript @group-h
Feature:
  Tests related to layout paragraphs. Anything that is related to the UI, MUST
  include javascript enabled. However, tests about paragraphs in non JS
  environment will be included.

  Background:
    Given collection content:
      | title         | state     |
      | Plaster molds | published |
    And custom_page content:
      | title                  | collection    |
      | Layout paragraphs test | Plaster molds |
    And I am logged in as a moderator
    And I go to the edit form of the "Layout paragraphs test" "custom page"

  Scenario: Test layout actions.
    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column |

    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | two columns |
      | Column widths    | 50-50       |
      | Background theme | light       |
      | Extra classes    | test-class  |
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column  |
      | two columns |

    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | three columns |
      | Background theme | light         |
      | Extra classes    | test-class    |
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column    |
      | two columns   |
      | three columns |

    When I delete from the "Custom page body" form field the layout paragraph in position 2
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column    |
      | three columns |

    When I "move down" from the "Custom page body" form field the layout paragraph in position 1
    Then the "Custom page body" form field should have the following layout paragraphs:
      | three columns |
      | one column    |

    When I "move up" from the "Custom page body" form field the layout paragraph in position 2
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column    |
      | three columns |

    When I "duplicate" from the "Custom page body" form field the layout paragraph in position 2
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column    |
      | three columns |
      | three columns |
    # Layout paragraphs store that changes have been performed in the UI, so
    # if we simply try to navigate elsewhere, an alert is raised about unsaved
    # changes and the next test fails. We need to save the changes.
    And I press "Publish"

  Scenario: Add paragraphs to layouts.
    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | one column |
      | Background theme | light      |
      | Extra classes    | test-class |
    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | two columns |
      | Column widths    | 50-50       |
      | Background theme | light       |
      | Extra classes    | test-class  |
    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | three columns |
      | Background theme | light         |
      | Extra classes    | test-class    |
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column    |
      | two columns   |
      | three columns |

    And I add a "Text" paragraph in the "Custom page body" form field:
      | layout position | 1                                                |
      | region          | content                                          |
      | Body            | type: ckeditor - value: This is a text paragraph |
    Then the layout in position "1" from the "Custom page body" form field should have the following structure:
      | content | text |
    And I add a "Heading" paragraph in the "Custom page body" form field:
      | layout position | 2                                       |
      | region          | first                                   |
      | Heading text    | type: text - value: Heading for 2column |
    And I add a "Text" paragraph in the "Custom page body" form field:
      | layout position | 2                                                        |
      | region          | first                                                    |
      | Body            | type: ckeditor - value: This should go under the heading |
    And I add a "Heading" paragraph in the "Custom page body" form field:
      | layout position | 2                                               |
      | region          | second                                          |
      | Heading text    | type: text - value: This is a heading paragraph |
    Then the layout in position "2" from the "Custom page body" form field should have the following structure:
      | first  | heading |
      | first  | text    |
      | second | heading |
    And I add a "Text" paragraph in the "Custom page body" form field:
      | layout position | 3                                                      |
      | region          | second                                                 |
      | Body            | type: ckeditor - value: This is another text paragraph |
    And I add a "Heading" paragraph in the "Custom page body" form field:
      | layout position | 3                                               |
      | region          | first                                           |
      | Heading text    | type: text - value: This is a heading paragraph |
    And I add a "Heading" paragraph in the "Custom page body" form field:
      | layout position | 3                                                     |
      | region          | third                                                 |
      | Heading text    | type: text - value: This is another heading paragraph |
    Then the layout in position "3" from the "Custom page body" form field should have the following structure:
      | first  | heading |
      | second | text    |
      | third  | heading |
    And I press "Publish"
