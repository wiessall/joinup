@api @javascript
Feature:
  Tests related to layout paragraphs. Anything that is related to the UI, MUST
  include javascript enabled. However, tests about paragraphs in non JS
  environment will be included.

  Background:
    Given custom_page content:
      | title                  |
      | Layout paragraphs test |
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
      | Background theme | light-wave  |
      | Extra classes    | test-class  |
    Then the "Custom page body" form field should have the following layout paragraphs:
      | one column  |
      | two columns |

    When I add a layout paragraph in the "Custom page body" form field with the following properties:
      | layout           | three columns |
      | Background theme | light-wave    |
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
