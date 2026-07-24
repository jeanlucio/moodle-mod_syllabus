<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['academicperiod'] = 'Academic period';
$string['actionnotallowed'] = 'Action not allowed';
$string['activities'] = 'Activities';
$string['activitycategory'] = 'Category';
$string['activityenddate'] = 'End date';
$string['activityname'] = 'Syllabus name';
$string['activitypoints'] = 'Points';
$string['activitystartdate'] = 'Start date';
$string['activitytitle'] = 'Activity title';
$string['activitytype'] = 'Type';
$string['addactivity'] = 'Add activity';
$string['addweek'] = 'Add week';
$string['cannotapproveown'] = 'You cannot approve or request changes on a plan you submitted yourself.';
$string['cannotsubmitstatus'] = 'This plan cannot be submitted from its current status.';
$string['categoryactivity'] = 'Grading and follow-up';
$string['categoryasynchronous'] = 'Asynchronous';
$string['categoryonline'] = 'Online';
$string['categoryplan'] = 'Plan content';
$string['categorysynchronous'] = 'Synchronous';
$string['categoryweek'] = 'Week content';
$string['changesrequestedreason'] = 'Reason for requested changes';
$string['characterisation'] = 'Characterisation';
$string['confirmdeleteactivity'] = 'Delete this activity? This cannot be undone.';
$string['confirmdeleteweek'] = 'Delete this week and all its activities? This cannot be undone.';
$string['confirmunpublish'] = 'Pull this plan back to draft and hide it again? You can resubmit it once you\'re done.';
$string['contents'] = 'Contents';
$string['contentshelp'] = 'Outline the topics covered, in the order they will be taught.';
$string['coursedescription'] = 'Course description';
$string['coursedescriptionhelp'] = "Summarise what the course covers and why it matters to the student's training.";
$string['courseenddate'] = 'Course end date';
$string['courseperiod'] = 'Course period';
$string['coursestartdate'] = 'Course start date';
$string['deadline'] = 'Deadline';
$string['defaultactivitytitle'] = 'Activity {$a}';
$string['defaultweektitle'] = 'Week {$a}';
$string['deleteactivity'] = 'Delete activity';
$string['deleteweek'] = 'Delete week';
$string['details'] = 'Details';
$string['detailshelp'] = "Describe what happens in this week's class.";
$string['discipline'] = 'Discipline';
$string['eventplanapproved'] = 'Plan approved';
$string['eventplanchangesrequested'] = 'Plan changes requested';
$string['eventplansubmitted'] = 'Plan submitted';
$string['finalassessment'] = 'Final assessment';
$string['generalreferences'] = 'References';
$string['generalreferenceshelp'] = 'List the required and recommended readings for the course.';
$string['gradingcriteria'] = 'Grading criteria';
$string['gradingcriteriahelp'] = 'Describe how this activity will be graded, including the answer key, if any.';
$string['interactiontools'] = 'Interaction tools';
$string['interactiontoolshelp'] = 'List the tools used to interact with students this week (forum, chat, meeting, etc.).';
$string['invalidarea'] = 'Invalid custom field area.';
$string['invalidfield'] = 'Invalid custom field.';
$string['managefieldsactivity'] = 'Manage activity fields';
$string['managefieldsplan'] = 'Manage plan fields';
$string['managefieldsweek'] = 'Manage week fields';
$string['messagebodyapproved'] = 'Your syllabus plan "{$a}" has been approved and is now visible to students and tutors.';
$string['messagebodychangesrequested'] = 'The coordinator requested changes on your syllabus plan "{$a->name}": {$a->reason}';
$string['messagebodysubmitted'] = 'The syllabus plan "{$a}" was submitted and is awaiting your review.';
$string['messageprovider:plan_approved'] = 'Plan approved';
$string['messageprovider:plan_changes_requested'] = 'Plan changes requested';
$string['messageprovider:plan_submitted'] = 'Plan submitted for review';
$string['messagesubjectapproved'] = 'Syllabus plan approved: {$a}';
$string['messagesubjectchangesrequested'] = 'Changes requested on your syllabus plan: {$a}';
$string['messagesubjectsubmitted'] = 'Syllabus plan submitted for review: {$a}';
$string['methodology'] = 'Methodology';
$string['methodologyhelp'] = 'Describe how classes will run: lectures, activities, tools, pace.';
$string['modulename'] = 'Syllabus';
$string['modulename_help'] = 'The Syllabus activity lets a teacher fill in a single structured course plan, submit it for coordination approval, and — once approved — automatically publish role-specific views to tutors and students.';
$string['modulenameplural'] = 'Syllabus activities';
$string['nosyllabusincourse'] = 'There are no syllabus plans in this course yet.';
$string['notawaitingreview'] = 'This plan is not currently awaiting review.';
$string['notes'] = 'Notes';
$string['noteshelp'] = 'Record any notes, pending actions or follow-ups for the tutoring team.';
$string['noweeksyet'] = 'No weeks have been added yet.';
$string['objectives'] = 'Objectives';
$string['objectiveshelp'] = 'List what students should be able to do by the end of the course.';
$string['onlyapprovedcanunpublish'] = 'Only an approved plan can be unpublished.';
$string['plannotavailable'] = 'This plan is not available yet.';
$string['pluginadministration'] = 'Syllabus administration';
$string['pluginname'] = 'Syllabus';
$string['presentationscript'] = 'Teacher and course presentation';
$string['presentationscripthelp'] = 'Introduce yourself and the course to the student, as a welcome message.';
$string['presentationvideo'] = 'Presentation video';
$string['privacy:metadata:syllabus'] = 'Records who submitted, reviewed and unpublished each syllabus plan.';
$string['privacy:metadata:syllabus:reviewedby'] = 'The ID of the user who last reviewed the plan.';
$string['privacy:metadata:syllabus:submittedby'] = 'The ID of the user who last submitted the plan.';
$string['privacy:metadata:syllabus:timereviewed'] = 'The time the plan was last reviewed.';
$string['privacy:metadata:syllabus:timesubmitted'] = 'The time the plan was last submitted.';
$string['privacy:metadata:syllabus:timeunpublished'] = 'The time the plan was last unpublished.';
$string['privacy:metadata:syllabus:unpublishedby'] = 'The ID of the user who last unpublished the plan.';
$string['reasonforchanges'] = 'Reason for the requested changes';
$string['requestchanges'] = 'Request changes';
$string['saved'] = 'Saved';
$string['saving'] = 'Saving...';
$string['schedule'] = 'Activity and grading schedule';
$string['sectionnavigation'] = 'Section navigation';
$string['sectionstatecomplete'] = 'Complete section';
$string['sectionstateempty'] = 'Empty section';
$string['sectionstatepartial'] = 'Partially filled section';
$string['status_approved'] = 'Approved';
$string['status_changesrequested'] = 'Changes requested';
$string['status_draft'] = 'Draft';
$string['status_submitted'] = 'Submitted for review';
$string['structuraleditblocked'] = 'Structural fields cannot be edited while this plan is awaiting review.';
$string['studentinstructions'] = 'Student instructions';
$string['studentinstructionshelp'] = 'Explain to the student what to do and how to complete this activity.';
$string['studyprogram'] = 'Programme';
$string['submitforreview'] = 'Submit for review';
$string['supplementarymaterial'] = 'Supplementary material';
$string['supplementarymaterialhelp'] = 'List optional readings and materials for further study.';
$string['supportmaterial'] = 'Support material';
$string['supportmaterialhelp'] = 'List the required readings and materials for this week.';
$string['syllabus:addinstance'] = 'Add a new syllabus';
$string['syllabus:review'] = 'Review and approve syllabus plans';
$string['syllabus:submit'] = 'Submit syllabus for review';
$string['syllabus:view'] = 'View a syllabus';
$string['syllabus:viewtutorview'] = 'View tutor plan';
$string['syncmeeting'] = 'Synchronous meeting';
$string['syncmeetingdate'] = 'Meeting date and time';
$string['syncmeetinglink'] = 'Access link';
$string['syncmeetingtopic'] = 'Topic';
$string['tabfullplan'] = 'Full plan';
$string['tabstudentplan'] = 'Student\'s plan';
$string['tabtutorplan'] = 'Tutor plan';
$string['teacher'] = 'Teacher';
$string['totalsmatch'] = 'Matches';
$string['totalsmismatch'] = "Doesn't match";
$string['totalworkload'] = 'Total workload (hours)';
$string['tutorguidance'] = 'Tutor guidance';
$string['tutorguidancehelp'] = 'Guide the tutor on how to support students through this activity.';
$string['typechat'] = 'Chat';
$string['typeforum'] = 'Forum';
$string['typegame'] = 'Game';
$string['typeother'] = 'Other';
$string['typequestionnaire'] = 'Questionnaire';
$string['typequiz'] = 'Quiz';
$string['typetask'] = 'Task';
$string['unpublish'] = 'Unpublish';
$string['visibilitymanaged'] = 'Availability is not set manually. The activity stays hidden while the plan is a draft or under review, and is shown to students and tutors automatically once the coordinator approves it.';
$string['week'] = 'Week';
$string['weekduration'] = 'Duration (hours)';
$string['weekenddate'] = 'End date';
$string['weeks'] = 'Weeks';
$string['weekstartdate'] = 'Start date';
$string['weektitle'] = 'Week title';
