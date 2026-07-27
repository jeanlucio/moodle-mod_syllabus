@mod @mod_syllabus @javascript
Feature: Syllabus approval workflow and role-based views
  In order to publish a course plan through a controlled review
  As a teacher, coordinator, tutor and student
  I need the plan to move through its workflow and show the right tab to each role

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | 1        | teacher1@example.com  |
      | manager1 | Manager   | 1        | manager1@example.com  |
      | tutor1   | Tutor     | 1        | tutor1@example.com    |
      | student1 | Student   | 1        | student1@example.com  |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | manager1 | C1     | manager        |
      | tutor1   | C1     | teacher        |
      | student1 | C1     | student        |
    And the following config values are set as admin:
      | requireacademicperiod  | 0 | mod_syllabus |
      | requirecourseperiod    | 0 | mod_syllabus |
      | requiretotalworkload   | 0 | mod_syllabus |
      | requirefinalassessment | 0 | mod_syllabus |
      | requireweekplanning    | 0 | mod_syllabus |
      | requireactivitytype    | 0 | mod_syllabus |
      | requireactivityperiod  | 0 | mod_syllabus |
    And the following "activities" exist:
      | activity | name        | course | idnumber  |
      | syllabus | My Syllabus | C1     | syllabus1 |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "My Syllabus"
    And I should see "Week 1"
    And I click on "Add activity" "button"
    And the field "Activity title" matches value "Activity 1"
    And I click on "Submit for review" "button"
    And I should see "Submitted for review" in the ".syllabus-status-badge" "css_element"
    And I log out
    And I log in as "manager1"
    And I am on "Course 1" course homepage
    And I follow "My Syllabus"
    And I should see "Submitted for review" in the ".syllabus-status-badge" "css_element"
    And I click on "Approve" "button"
    And I should see "Approved" in the ".syllabus-status-badge" "css_element"
    And I log out

  Scenario: A student sees only their own tab, with no tab bar at all
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "My Syllabus"
    Then I should see "Approved" in the ".syllabus-status-badge" "css_element"
    And ".syllabus-tab-nav" "css_element" should not exist

  Scenario: A tutor sees a tab bar and opens directly on the Tutor plan tab
    Given I log in as "tutor1"
    And I am on "Course 1" course homepage
    When I follow "My Syllabus"
    Then ".syllabus-tab-nav" "css_element" should exist
    And I should see "Tutor plan" in the ".syllabus-tab-nav .nav-link.active" "css_element"
    And I should see "Approved" in the ".syllabus-status-badge" "css_element"

  Scenario: A structural edit reopens review without hiding the plan from tutors and students
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "My Syllabus"
    When I set the field "Week title" to "Week 1 revised"
    And I click on "Duration (hours)" "field"
    # The "Saved" indicator fades back out 3 seconds after showing (see
    # amd/src/weeks_manager.js::showRowSaved()), too transient a signal to assert on
    # reliably - waiting past the autosave's own round trip and reloading is what actually
    # matters here: the plan regressing to submitted.
    And I wait "2" seconds
    And I reload the page
    Then I should see "Submitted for review" in the ".syllabus-status-badge" "css_element"
    And I should see "Unpublish"
    And I log out

    Given I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "My Syllabus"
    Then I should see "Submitted for review" in the ".syllabus-status-badge" "css_element"
    And I log out

    Given I log in as "tutor1"
    And I am on "Course 1" course homepage
    When I follow "My Syllabus"
    Then I should see "Submitted for review" in the ".syllabus-status-badge" "css_element"
