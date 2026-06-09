@api @group-b
Feature:

  Scenario: IOPEU Monitoring block functionality
    Given the following collection content:
      | uuid                                 | title                          |
      | ba527094-00bc-45de-98fb-c732c6c66fec | Interoperable Europe Community |
    And the following "custom_page" content:
      | title           |
      | Monitoring Page |
    And I append "block" to "field_paragraphs_body" field in "node" entity with following "Monitoring Page" title:
      | paragraph_block | interoperable_europe_monitoring |

    Given I am logged in as an "authenticated user"
    Given I visit the homepage of the "Monitoring Page" "custom_page"
    Then I should see the following table rows:
      | Total number of non-archived interoperability Solutions on the Portal | 0 |
      | Total number of archived interoperability Solutions on the Portal     | 0 |
      | Number of Interoperable Europe Solutions                              | 0 |
      | Number of Portal Solutions that have been mentioned in an assessment  | 0 |
      | Number of published interoperability assessment reports               | 0 |
      | Number of IOPEU Community members                                     | 0 |
      | Number of eLearning courses published on the portal                   | 0 |

    Given the following "solution" content:
      | title                        | state     | collection                           |
      | Test solution published      | published |                                      |
      | Test solution archived       | archived  |                                      |
      | Assessment report solution   | published | Solutions mentioned in an assessment |
      | Course solution              | draft     | Interoperable Europe Academy         |
    When I visit the homepage of the "Monitoring Page" "custom_page"
    Then I should see the following table rows:
      | Total number of non-archived interoperability Solutions on the Portal | 1 |
      | Total number of archived interoperability Solutions on the Portal     | 1 |
      | Number of Interoperable Europe Solutions                              | 0 |
      | Number of Portal Solutions that have been mentioned in an assessment  | 0 |
      | Number of published interoperability assessment reports               | 0 |
      | Number of IOPEU Community members                                     | 0 |
      | Number of eLearning courses published on the portal                   | 0 |

    Given the following users:
      | Username         |
      | Hepzibah Whitlow |
    And collection user membership:
      | collection                     | user             | state  |
      | Interoperable Europe Community | Hepzibah Whitlow | active |
    When I visit the homepage of the "Monitoring Page" "custom_page"
    Then the page should not be cached
    Then I should see the following table rows:
      | Total number of non-archived interoperability Solutions on the Portal | 1 |
      | Total number of archived interoperability Solutions on the Portal     | 1 |
      | Number of Interoperable Europe Solutions                              | 0 |
      | Number of Portal Solutions that have been mentioned in an assessment  | 0 |
      | Number of published interoperability assessment reports               | 0 |
      | Number of IOPEU Community members                                     | 1 |
      | Number of eLearning courses published on the portal                   | 0 |
    When I visit the homepage of the "Monitoring Page" "custom_page"
    Then the page should be cached

    Given the workflow state of the "Course solution" solution is changed to "published"
    When I visit the homepage of the "Monitoring Page" "custom_page"
    Then I should see the following table rows:
      | Total number of non-archived interoperability Solutions on the Portal | 2 |
      | Total number of archived interoperability Solutions on the Portal     | 1 |
      | Number of Interoperable Europe Solutions                              | 0 |
      | Number of Portal Solutions that have been mentioned in an assessment  | 0 |
      | Number of published interoperability assessment reports               | 0 |
      | Number of IOPEU Community members                                     | 1 |
      | Number of eLearning courses published on the portal                   | 1 |

    Given I am logged in as an "interoperability_validator"
    When I go to the homepage of the "Test solution published" solution
    And I click "Set interoperable flag" in the "Header" region
    And I press the Confirm button
    And I am logged in as an "authenticated user"
    And I visit the homepage of the "Monitoring Page" "custom_page"
    Then I should see the following table rows:
      | Total number of non-archived interoperability Solutions on the Portal | 2 |
      | Total number of archived interoperability Solutions on the Portal     | 1 |
      | Number of Interoperable Europe Solutions                              | 1 |
      | Number of Portal Solutions that have been mentioned in an assessment  | 0 |
      | Number of published interoperability assessment reports               | 0 |
      | Number of IOPEU Community members                                     | 1 |
      | Number of eLearning courses published on the portal                   | 1 |

    Given public_organisation assessments:
      | name     | organisation_type          |
      | Test Org | european_union_institution |
    Given report assessments:
      | name              | state     | provider |
      | Assessment Report | published | Test Org |
    And I append "binding_requirement" to "binding_requirement" field in "assessment" entity with following "Assessment Report" title:
      | asset | Assessment report solution, Test solution published |

    When I visit the homepage of the "Monitoring Page" "custom_page"
    Then I should see the following table rows:
      | Total number of non-archived interoperability Solutions on the Portal | 2 |
      | Total number of archived interoperability Solutions on the Portal     | 1 |
      | Number of Interoperable Europe Solutions                              | 1 |
      | Number of Portal Solutions that have been mentioned in an assessment  | 1 |
      | Number of published interoperability assessment reports               | 1 |
      | Number of IOPEU Community members                                     | 1 |
      | Number of eLearning courses published on the portal                   | 1 |
