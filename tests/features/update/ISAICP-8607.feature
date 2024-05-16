@api @group-clone
Feature: IOPE entities still show the text as expected.

  Scenario: Default view mode of events.
    # The label has an actual space at the end.
    Given I go to the "Visit the SEMIC2023 Expo! " event
    Then I should see the text "Published on: 09/10/2023"
    # Beginning of the body.
    And I should see the text "After the successful SEMIC EXPO last year,"
    # End of the body.
    And I should see the link "Expert.AI"

  Scenario: Tiles show the same body as before.
    Given I go to "/interoperable-europe/events"
    Then I should see the text "On the 11th and 12th of April, the Belgian University KU Leuven will be the stage of Interoperable Europe Academy 2024: the two-day live Academy for advanced digital skills in the field of digital government and interoperability. This year, public servants from all levels of the government, policy makers and students will have the chance to get all the ins and outs of the Interoperable Europe Act and to explore the next steps. The Interoperable Europe Academy 2024 will give the audience members an immersive (free of charge) teaching experience that will provide them an advanced understanding"
