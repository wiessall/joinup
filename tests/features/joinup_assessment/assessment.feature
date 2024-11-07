@api @group-a @demoAssessment
Feature: Document assessment

  Background:
    Given collection content:
      | title                                          | state     | content creation | moderation | uri                                                            |
      | Best practice for interoperability assessments | published | members          | yes        | http://data.europa.eu/w21/c5e99eae-6d31-47a0-b914-a4b9f4ea82cf |
    And users:
      | Username         | Roles     | E-mail                    | First name | Family name |
      | Notify moderator | moderator | notify_moderator@test.com | Notify     | Moderator   |

  Scenario: Create a document node out of a webform.
    When I am at "/form/online-form-for-assessments-demo"
    Then I should see the heading "Online form for assessments demo"

    When I fill in "Firstname" with "Henry"
    And I fill in "Lastname" with "Ford"
    And I fill in "The name of the initiative project" with "Pioneer in making automobiles"
    And I press "Submit"
    Then I should see "New submission added to Online form for assessments demo."
    And the following email should have been sent:
      | recipient | Notify moderator                                              |
      | subject   | Interoperable Europe Portal: Content has been proposed        |
      | body      | You can access the proposed new content at the following link |

    Given I am logged in as a moderator
    And I go to the latest document assessment
    And I click "Edit" in the "Entity actions" region
    And the "Abstract" field should contain "Pioneer in making automobiles"
    And the "Type of document" field should contain the "Interoperability Assessment" option
    And I press "Publish"

    Given I am not logged in
    And I go to the latest document assessment
    And I should see the following lines of text:
      | Firstname                          |
      | Henry                              |
      | Lastname                           |
      | Ford                               |
      | The name of the initiative project |
      | Pioneer in making automobiles      |
      | Type of document                   |
      | Interoperability Assessment        |
