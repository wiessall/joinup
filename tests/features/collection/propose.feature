@api @group-a
Feature: Proposing a collection
  In order to create a new collection on Interoperable Europe Portal
  As the product owner of a collection of software solutions
  I need to be able to propose a collection for inclusion on Interoperable Europe Portal

  # An anonymous user should be shown the option to add a collection, so that
  # the user will be aware that collections can be added by the public, even
  # though you need to sign in to do so.
  Scenario: Anonymous user needs to sign in before creating a collection
    Given users:
      | Username      | E-mail           |
      | Cecil Clapman | cecil@example.eu |
    Given CAS users:
      | Username | E-mail                | Password  | First name | Last name | Local username |
      | cclapman | clapman@ec.example.eu | abc123!#$ | Cecil J    | Clapman   | Cecil Clapman  |
    Given I am an anonymous user
    When I go to the propose collection form
    Then I should see the heading "Sign in to continue"
    When I fill in the following:
      | E-mail address | clapman@ec.example.eu |
      | Password       | abc123!#$             |
    And I press "Log in"
    Then I should see the heading "Propose collection"

  Scenario: Propose a collection
    Given owner content:
      | name                 | owner type |
      | Organisation example | Company    |
    And I am logged in as an "authenticated user"
    When I go to the propose collection form
    Then I should see the heading "Propose collection"
    And the following fields should not be present "Current workflow state, Langcode, Translation, Motivation"
    And the following field widgets should be present "Contact information, Owner"
    # Ensure that the description for the "Access url" is shown.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3196
    And I should see the description "Web page for the external Repository." for the "Access URL" field
    And I should see the description "This must be an external URL such as https://example.com." for the "Access URL" field

    # Check that validations errors are shown for required fields.
    When I press "Propose"
    Then I should see the following inline form error messages:
      | error messages                    |
      | Title field is required.          |
      | Description field is required.    |
      | Topic field is required.          |
      | Owner field is required.          |
      | Name field is required.           |
      | E-mail address field is required. |

    When I fill in the following:
      | Title                 | Ancient and Classical Mythology                                                                      |
      | Abstract              | The seminal work on the ancient mythologies of the primitive and classical peoples of the Discworld. |
      | Description           | The seminal work on the ancient mythologies of the primitive and classical peoples of the Discworld. |
      | Geographical coverage | Belgium                                                                                              |
      # Contact information data.
      | Name                  | Contact person                                                                                       |
      | E-mail                | contact_person@example.com                                                                           |
      | Search keywords       | government                                                                                         |
    Then I should see the text "Relevant keywords improve the search ranking of your content."
    When I select "Demography" options from "Topic"
    # The owner field should have a help text.
    And I should see the text "The Owner is the organisation that owns this entity and is the only responsible for it."
    # Click the button to select an existing owner.
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Organisation example"
    And I press "Propose"
    And I should see the heading "Ancient and Classical Mythology"
    # Regression test for the keywords field in the about page.
    And I should not see the text "Search keywords" in the "Content" region
    And I should not see the text "Some keyword" in the "Content" region
    And I should not see a logo on the header
    And I should see "Thank you for proposing a collection. Your request is currently pending approval by the site administrator."

    # The user that proposed the collection should be auto-subscribed.
    And the "Ancient and Classical Mythology" collection should have 1 active member
    # The overview and about links should be added automatically in the menu.
    And I should see the following navigation menu items in the specified order:
      | text     |
      | Overview |
    When I click the contextual link "Add new page" in the "Header" region
    Then I should see the heading "Add custom page"
    When I fill in the following:
      | Title    | About this group          |
      | Abstract | Abstract about this group |
    And I select "Demography" options from "Topic"
    And I press "Publish"
    Then I should see the success message "Custom page About this group has been created."

  Scenario: Propose a collection with a duplicate name
    Given collection content:
      | title                  |
      | The Ratcatcher's Guild |
    Given I am logged in as a user with the "authenticated" role
    When I go to the propose collection form
    And I fill in the following:
      | Title       | The Ratcatcher's Guild                                            |
      | Abstract    | Some abstract                                                     |
      | Description | A guild of serious men with sacks in which things are struggling. |
      # Contact information data.
      | Name        | Some contact                                                      |
      | E-mail      | some.contact@example.com                                          |
    And I press "Save as draft"
    Then I should see the inline form error message "Content with title The Ratcatcher's Guild already exists. Please choose a different title."

  @javascript @generateMedia @generateMediaTypes:collection_logo
  Scenario: Propose collection form fields should be organized in tabs.
    Given owner content:
      | name                 | owner type |
      | Organisation example | Company    |

    Given I am logged in as an "authenticated user"
    When I go to the propose collection form
    Then the following fields should be visible "Title, Abstract, Description, Topic"
    And the following field widgets should be visible "Owner"
    And the following fields should not be visible "Geographical coverage"
    And the following fields should not be present "Affiliates"
    And the following field widgets should be visible "Contact information"

    # Fill the mandatory fields.
    And I fill in "Title" with "Just a proposal"
    And I fill in "Abstract" with "Just a proposal"
    And I enter "Nothing..." in the "Description" wysiwyg editor
    And I select "Employment and Support Allowance" options from "Topic"
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Organisation example"
    And I press "Add owner"
    And I fill in the following:
      | E-mail | invisible.man@example.com |
      | Name   | Invisible Man             |
    And I press "Create contact information"

    # Close "Main fields" to check its fields visibility.
    When I expand the "Additional fields" accordion section
    And I collapse the "Main fields" accordion section
    Then the following fields should not be visible "Title, Abstract, Description, Topic"
    And the following field widgets should not be visible "Owner"
    And the following fields should be visible "Geographical coverage"
    And the following fields should not be present "Affiliates"
    And the following field widgets should not be visible "Contact information"

    # As a user, editing a collection, solution, event or news item, I want to
    # be able to pick-up a pre-uploaded image when editing the logo as an
    # alternative to the image upload.
    And I select image #3 as collection logo
    And I fill in "Alternative text" with "Alternative logo text"
    And I press "Propose"
    Then I should see the success message "Thank you for proposing a collection. Your request is currently pending approval by the site administrator."
    And the "Just a proposal" collection logo is image #3

    Given I go to the edit form of the "Just a proposal" collection
    And I expand the "Additional fields" accordion section
    And I remove the file from the "Logo" field

    # Upload also works.
    When I attach the file "logo.png" to "Logo"
    And I fill in "Alternative text" with "Alternative logo text"
    And I press "Propose"

    When I go to the edit form of the "Just a proposal" collection
    And I expand the "Additional fields" accordion section
    Then I should see the link "logo.png"

  @javascript
  # This is a regression test for a bug where nothing was happening when
  # submitting the collection form after not filling some of the required
  # fields. This was due the HTML5 constraint validation not being able to
  # focus the wanted element because it was hidden by css.
  # See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3057
  Scenario: Browser validation errors should focus the correct field group.
    Given I am logged in as an "authenticated user"
    When I go to the propose collection form
    Then the "Main fields" accordion section should be active
    # This form has two elements only that have browser-side validation.
    When I fill in "Title" with "Constraint validation API"
    And I fill in "Abstract" with "Constraint validation API"
    And I expand the "Additional fields" accordion section
    And I press "Propose"
    # Our code should have changed the active accordion item now. A browser message will
    # be shown to the user.
    Then the "Main fields" accordion section should be active
    # Fill the required fields.
    When I select "Demography" options from "Topic"
    And I fill in the following:
      | Name   | Contact person             |
      | E-mail | contact_person@example.com |
    And I press "Propose"
    # The backend-side validation will kick in now.
    Then I should see the error message "Description field is required."

  @email @javascript
  Scenario: Email validator after submit a Collection/Solution.
    Given owner content:
      | name               | owner type |
      | Organisation valid | Company    |

    Given I am logged in as an "authenticated user"
    Then I go to the propose collection form
    And I fill in "Title" with "Some email validation"
    And I fill in "Abstract" with "Some email validation"
    And I enter "Nothing..." in the "Description" wysiwyg editor
    And I select "Demography" options from "Topic"
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Organisation valid"
    And I press "Add owner"
    And I fill in the following:
      | Name   | Contact person                         |
      | E-mail | some_unique_contact_person@example.com |
    And I press "Create contact information"
    And I press "Propose"
    Then I should see the heading "Some email validation"

    # Email confirmation occurs only for published groups.
    And the following email should not have been sent:
      | recipient_mail     | some_unique_contact_person@example.com                                                                                                |
      | subject            | Interoperable Europe Portal - Please validate your contact details                                                                    |
      | body               | Please confirm your email address some_unique_contact_person@example.com, which is set in your contact information for the following: |
      | signature_required | no                                                                                                                                    |
      | html               | yes                                                                                                                                   |

    When I click "Add solution" in the plus button menu
    Then I fill in "Title" with "Rolly email validation"
    And I fill in "Abstract" with "Meaningless summary"
    And I enter "Nothing..." in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    And I fill in "Website URL" with "http://example.com/john-doe"
    Then I press "Create contact information"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"

    # Email confirmation occurs only for published groups.
    And the following email should not have been sent:
      | recipient_mail     | contact_Rapazoglou@example.com                                                                                                |
      | subject            | Interoperable Europe Portal - Please validate your contact details                                                            |
      | body               | Please confirm your email address contact_Rapazoglou@example.com, which is set in your contact information for the following: |
      | signature_required | no                                                                                                                            |
      | html               | yes                                                                                                                           |

    # Email confirmations are sent on group publishing.
    Given I am logged in as a "moderator"
    When I go to the edit form of the "Some email validation" collection
    And I press "Publish"
    # @todo Re-enable in ISAICP-8992
    Then the following email should have been sent:
      | recipient_mail     | some_unique_contact_person@example.com                                                                                                |
      | subject            | Interoperable Europe Portal - Please validate your contact details                                                                    |
      | body               | Please confirm your email address some_unique_contact_person@example.com, which is set in your contact information for the following: |
      | signature_required | no                                                                                                                                    |
      | html               | yes                                                                                                                                   |
    When I go to the edit form of the "Rolly email validation" solution
    And I press "Confirm"
    And I press "Publish"
    And the following email should have been sent:
      | recipient_mail     | contact_Rapazoglou@example.com                                                                                                |
      | subject            | Interoperable Europe Portal - Please validate your contact details                                                            |
      | body               | Please confirm your email address contact_Rapazoglou@example.com, which is set in your contact information for the following: |
      | signature_required | no                                                                                                                            |
      | html               | yes                                                                                                                           |

    # Contact us page should return access denied,
    # because contact info emails are not published.
    When I go to "/collection/some-email-validation/contact"
    Then I should see the heading "Access denied"

  @javascript
  Scenario: Allowing users to upload SVGs.
    Given owner content:
      | name    | owner type |
      | Svinish | Company    |
    And I am logged in as an "authenticated user"
    When I go to the propose collection form
    Then I should see the heading "Propose collection"
    When I fill in "Title" with "SVG API example"
    And I fill in "Abstract" with "SVG API example"
    And I enter "We do not care that much about descriptions." in the "Description" wysiwyg editor
    And I select "Employment and Support Allowance" options from "Topic"
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Svinish"
    And I press "Add owner"
    And I fill in the following:
      | E-mail | basic.svg.api@example.com |
      | Name   | BasicSvgAPI Contact       |
    And I press "Create contact information"
    When I select "Data gathering and processing" options from "Topic"

    When I expand the "Additional fields" accordion section
    # Shows error when svg is invalid or unsafe and fails to upload.
    And I attach the file "error.svg" to "Logo"
    Then I should see the inline form error message "Invalid or unsafe SVG file."
    And I attach the file "simple.svg" to "Logo"
    Then I should not see the inline form error message "Invalid or unsafe SVG file."
    And I fill in "Alternative text" with "Alternative logo text"
    And I press "Propose"
    Then I should see the heading "SVG API example"
    And I should own the "SVG API example" collection

  Scenario: The node add collection route redirects to the propose form.
    Given I am logged in as a user with the "authenticated" role
    When I go to "/node/add/collection"
    Then the url should match "/propose/collection"
    And I should see the heading "Propose collection"
    When I go to "/fr/node/add/collection"
    Then the url should match "/fr/propose/collection"
    And I should see the heading "Propose collection"
