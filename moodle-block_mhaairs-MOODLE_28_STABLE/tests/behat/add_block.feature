@block @block_mhaairs
Feature: Add block

  Background:
    Given the following "courses" exist:
        | fullname | shortname | category |
        | Course 1 | C1        | 0        |
    And I log in as "admin"


    @javascript
    Scenario: Add block when site not configured.

        And I follow "Courses"
        And I follow "Course 1"

        When I follow "Turn editing on"
        And I add the "McGraw-Hill AAIRS" block

        Then I should see "The site requires further configuration. Please contact your site admin."

    @javascript
    Scenario: Add block when site is configured.

        Given I navigate to "Settings" node in "Site administration > Plugins > Blocks > McGraw-Hill AAIRS"
        And I set the mhaairs customer number and shared secret
        And I press "Save changes"
        And I set the field "McGraw-Hill Campus" to "checked"
        And I press "Save changes"

        And I follow "Courses"
        And I follow "Course 1"

        When I follow "Turn editing on"
        And I add the "McGraw-Hill AAIRS" block

        Then I should see "The block requires further configuration. Please configure the block."

