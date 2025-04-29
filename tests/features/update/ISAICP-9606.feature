@api @group-clone
Feature: Sample test updated solutions

  @update:joinup_core_deploy_200400
  Scenario: Test that solutions that were not moderated, are.
    # Solution with NID 720827 is not moderated.
    Given I go to the "eProcurement Demonstrators" solution
    # Test the response HTML as if we only check the text moderated, the "Non
    # moderated" text will also be matched.
    Then the response should contain "<div class=\"field--name-group-moderation solution__group-moderation\">Moderated</div>"

    Given I go to "/collection/free-and-open-source-software/solution/mautic"
    Then I should see the text "Last update 26/02/2025"
