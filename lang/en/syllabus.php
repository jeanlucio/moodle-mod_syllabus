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
$string['activitytypecategory'] = 'Activity type and category';
$string['activitytypehelp'] = 'Choose the LMS tool used and whether the activity is synchronous, asynchronous or online.';
$string['activitytypehelpfull'] = 'Type: indicate which LMS tool this activity uses (Forum, Questionnaire, Task, Game, etc.) or mark it as In-person, when applicable. Category: Synchronous for activities that happen live; Asynchronous for ones the student completes on their own time; Online as the general category for anything that happens in the LMS with no set time. Keep in mind the institutional rule (DEaD Guideline 10/2021): at least 1 asynchronous activity is required for every 10 hours of the discipline\'s workload.';
$string['addactivity'] = 'Add activity';
$string['addweek'] = 'Add week';
$string['allchangessaved'] = 'All changes saved';
$string['cannotapproveown'] = 'You cannot approve or request changes on a plan you submitted yourself.';
$string['cannotsubmitstatus'] = 'This plan cannot be submitted from its current status.';
$string['categoryactivity'] = 'Grading and follow-up';
$string['categoryasynchronous'] = 'Asynchronous';
$string['categoryhelp'] = 'Model guidance';
$string['categoryonline'] = 'Online';
$string['categoryplan'] = 'Plan content';
$string['categorysynchronous'] = 'Synchronous';
$string['categoryweek'] = 'Week content';
$string['changesrequestedreason'] = 'Reason for requested changes';
$string['characterisation'] = 'Characterisation';
$string['characterisationhelp'] = 'Course, Discipline name and Teacher are filled in automatically; fill in Academic period, Course period and Total workload — the Presentation video is optional.';
$string['characterisationhelpfull'] = 'The source template asks for Course, Discipline name and Teacher — these three are filled in automatically from Moodle (the course\'s category, its name, and whoever submitted the plan) and are not editable fields here. Fill in only: Academic period (e.g. 2026.1); Course period, with the start and end date per the course\'s calendar for the term; Total workload, per the discipline\'s pedagogical course plan (PPC); and, optionally, the Presentation video — a video of up to 5 minutes introducing yourself and the course, covering 5 blocks: (1) opening and welcome, (2) the course\'s objectives and relevance, (3) methodology, how synchronous and asynchronous moments connect, (4) follow-up and assessment, and (5) closing with an invitation to participate; the full script (the video\'s transcript) belongs in the Teacher and course presentation field just below.';
$string['collapseall'] = 'Collapse all';
$string['confirmdeleteactivity'] = 'Delete this activity? This cannot be undone.';
$string['confirmdeleteweek'] = 'Delete this week and all its activities? This cannot be undone.';
$string['confirmunpublish'] = 'Pull this plan back to draft and hide it again? You can resubmit it once you\'re done.';
$string['contents'] = 'Contents';
$string['contentshelp'] = 'Outline the topics covered, in the order they will be taught.';
$string['contentshelpfull'] = 'Detail the content to be covered throughout the discipline, in line with the description and objectives defined above — usually organised in the same order it will appear across the weeks/classes.';
$string['coursedescription'] = 'Course description';
$string['coursedescriptionhelp'] = "Summarise what the course covers and why it matters to the student's training.";
$string['coursedescriptionhelpfull'] = 'Write a panoramic description of the topics covered in the discipline. Check the official syllabus available in the course\'s pedagogical plan (PPC) — you don\'t have to limit yourself to it, but everything in it must be covered here.';
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
$string['detailshelpfull'] = 'Write a simple, dialogic text, in the first person, speaking directly to the student — welcome them to the week, introduce the central theme (a guiding question helps give it purpose) and connect it to the previous class. Include this week\'s specific objectives (\'By the end of this class, you will be able to...\', using action verbs) and a step-by-step study route: first the asynchronous materials to explore, then the synchronous meeting (if any), and finally the week\'s graded activity — without repeating the activity\'s full instructions here, since those have their own field.';
$string['discipline'] = 'Discipline';
$string['eventplanapproved'] = 'Plan approved';
$string['eventplanchangesrequested'] = 'Plan changes requested';
$string['eventplansubmitted'] = 'Plan submitted';
$string['expandall'] = 'Expand all';
$string['finalassessment'] = 'Final assessment';
$string['finalassessmenthelp'] = 'Mark the activity that represents the discipline\'s Final assessment, if any.';
$string['finalassessmenthelpfull'] = 'Check this box for the activity that represents the discipline\'s Final assessment — it is meant for students who did not reach a discipline average (MD) of 70 or higher, provided they reached at least 40, and should cover the content taught across the whole discipline. A note on points: the source template has every activity worth 100 points on its own; in this plugin, every activity\'s points in the discipline (or in each stage, if there is more than one) add up to 100 instead — set this activity\'s points with that distribution in mind.';
$string['generalreferences'] = 'References';
$string['generalreferenceshelp'] = 'List the required and recommended readings for the course.';
$string['generalreferenceshelpfull'] = 'List the discipline\'s overall required and complementary bibliography, formatted per your institution\'s citation standard — check the references already defined in the course\'s pedagogical plan (PPC).';
$string['gradingcriteria'] = 'Grading criteria';
$string['gradingcriteriahelp'] = 'Describe how this activity will be graded, including the answer key, if any.';
$string['gradingcriteriahelpfull'] = 'Describe the grading criteria and, if there is an answer key, include it here. For objective activities (quizzes), never reference just the question number and the correct option\'s letter — Moodle can shuffle both questions and answer options between students, so transcribe the full text of the correct option, followed by a short justification. For subjective activities, detail the criteria as topics with each one\'s point value (e.g. argumentative quality — 5 points; standard-language use — 5 points; proper use of references — 5 points).';
$string['interactiontools'] = 'Interaction tools';
$string['interactiontoolshelp'] = 'List the tools used to interact with students this week (forum, chat, meeting, etc.).';
$string['interactiontoolshelpfull'] = 'Detail how the interaction tools (forum, virtual cafe, chat, email, etc.) will be used in this class — for example, a virtual-cafe forum for a group diagnostic, an introduction chat, or email as the main channel for questions, stating the expected response time.';
$string['invalidarea'] = 'Invalid custom field area.';
$string['invalidfield'] = 'Invalid custom field.';
$string['managefieldsactivity'] = 'Manage activity fields';
$string['managefieldshelp'] = 'Manage model guidance fields';
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
$string['methodologyhelpfull'] = 'Describe how classes will run and which teaching strategies will be used — always connecting each strategy and activity back to the objectives defined above: if an objective asks the student to apply a piece of knowledge, which activity provides that application? Organise the text around three threads: Synchronous meetings (the space to dig into complex topics, debate and build knowledge together), Asynchronous activities (video lectures, base texts, podcasts and other materials the student explores at their own pace, forming the groundwork for the synchronous meetings) and Assessment activities (processual and continuous by nature, meant not to measure memorisation but to have the student apply, analyse and synthesise what they learned, always aligned with that unit\'s objectives). Write in the first person, speaking directly to the student.';
$string['modulename'] = 'Syllabus';
$string['modulename_help'] = 'The Syllabus activity lets a teacher fill in a single structured course plan, submit it for coordination approval, and — once approved — automatically publish role-specific views to tutors and students.';
$string['modulenameplural'] = 'Syllabus activities';
$string['nosyllabusincourse'] = 'There are no syllabus plans in this course yet.';
$string['notawaitingreview'] = 'This plan is not currently awaiting review.';
$string['notes'] = 'Notes';
$string['noteshelp'] = 'Record any notes, pending actions or follow-ups for the tutoring team.';
$string['noteshelpfull'] = 'Record here any observation, resource or arrangement the tutor needs to organise ahead of time for this class/activity — if there is nothing to note, this field can be left blank.';
$string['noweeksyet'] = 'No weeks have been added yet.';
$string['objectives'] = 'Objectives';
$string['objectiveshelp'] = 'List what students should be able to do by the end of the course.';
$string['objectiveshelpfull'] = 'Set the learning objectives to be reached — what the student is expected to learn during and by the end of the discipline. They can be split into a general objective and specific objectives, always focused on the student\'s development, not the content itself. Start each objective with a verb in the infinitive, for example: Understand the framework the Brazilian education system rests on; Apply IT knowledge to education; Reflect on the use of technology in educational processes; Get to know the Moodle virtual learning environment.';
$string['onlyapprovedcanunpublish'] = 'Only an approved plan can be unpublished.';
$string['plannotavailable'] = 'This plan is not available yet.';
$string['pluginadministration'] = 'Syllabus administration';
$string['pluginname'] = 'Syllabus';
$string['presentationscript'] = 'Teacher and course presentation';
$string['presentationscripthelp'] = 'Introduce yourself and the course to the student, as a welcome message.';
$string['presentationscripthelpfull'] = 'Write the full transcript (the script) of the presentation video mentioned in Characterisation, up to 5 minutes long, split into 5 blocks: (1) Opening and welcome, a warm environment, with a brief introduction of yourself; (2) The heart of the discipline, the general objective and relevance of the discipline, and the main skills the student will develop; (3) Our learning journey, the methodology, explaining how synchronous and asynchronous moments connect, with a concrete example from the discipline; (4) Follow-up and assessment, how the student\'s progress will be assessed, emphasising its formative nature rather than just grades; (5) Closing and next steps, a motivating message and a clear call to the student\'s first action in the LMS (e.g. taking part in the Introduction forum).';
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
$string['studentinstructionshelpfull'] = 'Write a detailed, clear and objective prompt, speaking directly to the student. The text must include: the activity\'s purpose; its explicit connection to this class\'s objectives and skills; the task itself, what to do, precisely and, where possible, broken into steps; and which of this class\'s study materials the student should draw on to complete it.';
$string['studyprogram'] = 'Programme';
$string['submitforreview'] = 'Submit for review';
$string['supplementarymaterial'] = 'Supplementary material';
$string['supplementarymaterialhelp'] = 'List optional readings and materials for further study.';
$string['supplementarymaterialhelpfull'] = 'List, as topics, complementary materials to deepen understanding of this class\'s content — articles, books, images, videos and the like. Check the licence when they aren\'t your own material, and note the LMS resource used for each item.';
$string['supportmaterial'] = 'Support material';
$string['supportmaterialhelp'] = 'List the required readings and materials for this week.';
$string['supportmaterialhelpfull'] = 'List, as topics, the materials that form the base of this class — preferably original/authored material; when they aren\'t, check the licence before using them. For each item, note the LMS resource used (page, PDF, video) and the link or access location. Follow proper citation standards and copyright/anti-plagiarism regulations.';
$string['syllabus:addinstance'] = 'Add a new syllabus';
$string['syllabus:review'] = 'Review and approve syllabus plans';
$string['syllabus:submit'] = 'Submit syllabus for review';
$string['syllabus:view'] = 'View a syllabus';
$string['syllabus:viewtutorview'] = 'View tutor plan';
$string['syncmeeting'] = 'Synchronous meeting';
$string['syncmeetingdate'] = 'Meeting date and time';
$string['syncmeetinghelp'] = 'Date, time, link and topic of this week\'s live meeting, if there is one.';
$string['syncmeetinghelpfull'] = 'Fill in this week\'s live meeting date, time and access link, plus a short topic summarising what will be covered — written as a direct invitation to the student. After the meeting happens, its recording should also be made available in the LMS, in the same spot. Keep in mind the institutional rule (DEaD Guideline 10/2021): the discipline as a whole must include at least 2 synchronous (or in-person, for courses with mandatory in-person meetings) activities — not every week needs one, but the discipline overall must add up to at least 2.';
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
$string['tutorguidancehelpfull'] = 'State how the tutor should follow up on this activity — for example, reviewing the submitted text or post, checking completion per unit/module, and how to record each activity\'s points.';
$string['typechat'] = 'Chat';
$string['typeforum'] = 'Forum';
$string['typegame'] = 'Game';
$string['typeother'] = 'Other';
$string['typepresential'] = 'In-person';
$string['typequestionnaire'] = 'Questionnaire';
$string['typequiz'] = 'Quiz';
$string['typetask'] = 'Task';
$string['unpublish'] = 'Unpublish';
$string['viewmodelguidance'] = 'View guidance';
$string['visibilitymanaged'] = 'Availability is not set manually. The activity stays hidden while the plan is a draft or under review, and is shown to students and tutors automatically once the coordinator approves it.';
$string['week'] = 'Week';
$string['weekduration'] = 'Duration (hours)';
$string['weekenddate'] = 'End date';
$string['weekplanning'] = 'Week duration and period';
$string['weekplanninghelp'] = 'Set this class\'s duration and period according to the discipline\'s total workload and calendar.';
$string['weekplanninghelpfull'] = 'Duration: estimate how many hours this class needs, taking into account the discipline\'s total workload and how much material and how many activities you plan to make available — the sum of every class\'s duration should add up to the Total workload in the Characterisation block above. Account for the real time a student needs to read, watch videos, answer the questionnaire and take part in the synchronous meeting, if any. Class period: enter the start and end date per the course\'s schedule for this week.';
$string['weeks'] = 'Weeks';
$string['weekstartdate'] = 'Start date';
$string['weektitle'] = 'Week title';
