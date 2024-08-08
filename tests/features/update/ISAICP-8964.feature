@api @group-clone
Feature: Tests topic meta entity bugfix

  @update:joinup_core_deploy_111101
  Scenario: Topic meta entity admin page can be accessed by moderators
    Given I am logged in as a moderator
    And I visit "/admin/homepage-topics"
    Then the response status code should be 200
    And I should see the heading "Topics"
    And I should see the following links:
      | Advanced data analytics          |
      | AI Chatbot                       |
      | AI Facial recognition            |
      | AI Image generation              |
      | AI Machine translation           |
      | AI Named entity recognition      |
      | AI Speech recognition            |
      | AI Video analytics               |
      | Air and space transport          |
      | Artificial intelligence          |
      | Banking and financial services   |
      | Blockchain                       |
      | Business and competition         |
      | Collaboration                    |
      | Content and knowledge management |
      | Culture and education            |
      | Demography and population        |
      | Digital government               |
      | Digital innovation               |
      | Digital-ready policymaking       |
      | Economics                        |
      | eDelivery and eInvoicing         |
      | Education                        |
      | eGovernment                      |
      | eHealth                          |
      | eIdentity and eSignature         |
      | eInclusion                       |
      | Employment                       |
      | Energy and environment           |
      | eParticipation and eVoting       |
      | eProcurement                     |
      | European Policies                |
      | Extended reality                 |
      | Food                             |
      | Geospatial                       |
      | Health                           |
      | High performance computing       |
      | Human resources                  |
      | ICT                              |
      | ICT security                     |
      | Indoor position systems          |
      | Industry and production          |
      | Innovation                       |
      | Integrated logistics             |
      | Internet of Things               |
      | Land transport                   |
      | Languages and translation        |
