# 🔐 Security & Compliance

* Capability-based access control: `mod/syllabus:view`, `mod/syllabus:submit`,
  `mod/syllabus:review` and `mod/syllabus:viewtutorview` gate every action and view by role
* Every web service resolves the received `cmid`, derives the real course module context, and
  calls `validate_context()` before any capability check — never operates on an isolated id
  without binding it to its own course, verified by a dedicated cross-instance isolation test
  suite (`tests/cross_instance_security_test.php`)
* Web services are consumed via `core/ajax`, whose transport already includes and validates the
  session key automatically
* A user cannot approve or request changes on a plan they submitted themselves, even holding
  the review capability — enforced server-side in `plan_state_manager::approve()`, never left
  to the UI alone
* Workflow guards (wrong status for a transition, self-approval) raise a translated
  `moodle_exception`, never a `coding_exception` — these are business-rule outcomes a normal
  user action can trigger, not programmer mistakes
* Tutor/student content access is gated in `view.php` by the course module's own `visible`
  flag — set only by `plan_state_manager::approve()`/`unpublish()` — rather than the plan's
  literal status, so a structural edit that reopens an approved plan for review never blocks
  content already visible to tutors/students. The gate is still enforced server-side,
  independent of Moodle's course-page hidden-activity filtering, which does not itself block
  direct access for a role holding `moodle/course:viewhiddenactivities`
* Narrative content is Custom Fields API data, rendered through `format_text()` — never printed
  raw
* Moodle External API compliant
* Privacy API fully implemented: the plan's content is the course's own pedagogical record, not
  personal data — only the three workflow actor references (submitted/reviewed/unpublished by)
  are exported/anonymised per user, since the plan itself is shared, ongoing institutional
  content that must survive a data-deletion request, not a per-user submission
* Coordinator review notes (`syllabus_review_notes`) are the one deliberate exception to that
  anonymise-only rule: a note's entire substance is the reviewer's own authored text, so
  deleting a reviewer's data deletes the note row outright rather than anonymising a reference
  on it
