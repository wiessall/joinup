@api @eif_community @terms @group-e
Feature:
  As the owner of the EIF toolbox
  in order to make it easier for users to find appropriate solutions
  I need to be able to present them in a nice searchable way.

  @javascript
  Scenario: Present and search the solutions.
    Given owner:
      | name | type    |
      | ACDC | Company |
    And collections:
      | title   | state     |
      | Killjoy | validated |
      | Sage    | validated |
      | Phoenix | draft     |
    And solutions:
      | title    | eif reference                      | eif category    | collection | state     |
      | Jordan 1 | Recommendation 1, Recommendation 2 | Common services | Killjoy    | validated |
      | Jordan 2 | Recommendation 4                   | Common services | Sage       | validated |
      | Jordan 3 | Recommendation 5, Recommendation 2 | Common services | Sage       | draft     |
      | Jordan 4 | Recommendation 3, Recommendation 1 | Common services | Phoenix    | validated |
      | Jordan 5 | Recommendation 4                   | Common services | Killjoy    | draft     |

    # Visit the toolbox page once to ensure that solutions are properly
    # re-indexed when an EIF perspective is created or deleted and that
    # cache is invalidated properly.
    Given I visit the "Online Assistant" custom page
    Then I should not see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |

    Given the following EIF perspective data for the "Jordan 1" solution:
      | Description      | It will be an amazing principle.                                                                                     |
      | Type of solution | Common services                                                                                                      |
      | Solution type    | Assessment tools, Generic tools                                                                                      |
      | Layer            | Interoperability Layer 3: Legal interoperability                                                                     |
      | Model            | Basic Component 1: Coordination function                                                                             |
      | Principle        | Underlying Principle 2: Openness, Underlying Principle 3: Transparency, Underlying Principle 8: Security and privacy |
    And the following EIF perspective data for the "Jordan 2" solution:
      | Description   | It will be an amazing principle.                 |
      | Solution type | Assessment tools, Generic tools                  |
      | Layer         | Interoperability Layer 3: Legal interoperability |
      | Model         | Basic Component 1: Coordination function         |
      | Principle     | Underlying Principle 3: Transparency             |
    And the following EIF perspective data for the "Jordan 3" solution:
      | Description   | It will be an amazing principle.                      |
      | Solution type | Common services                                       |
      | Principle     | Underlying Principle 11: Preservation of information  |
      | Layer         | Interoperability Layer 1: Interoperability governance |
      | Model         | Basic Component 4: Open data                          |
    And the following EIF perspective data for the "Jordan 4" solution:
      | Description   | It will be an amazing principle.                      |
      | Solution type | Common services                                       |
      | Principle     | Underlying Principle 11: Preservation of information  |
      | Layer         | Interoperability Layer 1: Interoperability governance |
      | Model         | Basic Component 4: Open data                          |

    When I visit the "Online Assistant" custom page
    And I should see the following filter categories in the correct order:
      | Underlying principles  |
      | Interoperability layer |
      | Conceptual model       |
      | Solution category      |
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the principles.
    When I click "Transparency" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |

    # Test multiple options. Both "Transparency" and "Security and privacy" are
    # selected.
    When I click "Security and privacy" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
    But I should not see the following headings:
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |

    # Reset the options.
    When I click "Transparency" in the "Content" region
    When I click "Security and privacy" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the interoperability layer.
    When I click "Legal interoperability" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |

    # Test selecting terms from more than one vocabularies.
    When I click "Openness" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
    But I should not see the following headings:
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |

    # Reset the options.
    When I click "Openness" in the "Content" region
    When I click "Legal interoperability" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the conceptual model.
    When I click "Open data" in the "Content" region
    Then I should see the following headings:
      | Jordan 4 |
    But I should not see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 3 |
      | Jordan 5 |

    # Reset the options.
    When I click "Open data" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Test the solution category.
    When I click "Assessment tools" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
    Then I should not see the following headings:
      | Jordan 3 |
      | Jordan 4 |
      | Jordan 5 |

    # Reset the options.
    When I click "Assessment tools" in the "Content" region
    Then I should see the following headings:
      | Jordan 1 |
      | Jordan 2 |
      | Jordan 4 |
    But I should not see the following headings:
      | Jordan 3 |
      | Jordan 5 |

    # Ensure that deleting an EIF perspective, the online assistant is updated
    # as well.
    Given I delete the EIF perspective of the "Jordan 1" solution
    When I visit the "Online Assistant" custom page
    And I should not see the heading "Jordan 1"
