# 📖 Usage

1. Add a **Syllabus** activity to a course. It stays hidden from students and tutors until a
   plan is approved.
2. As the teacher, fill in the plan's fields — course description, objectives, contents,
   methodology, references, grading criteria — each one autosaving as you type, with a
   "saving…/saved" indicator next to the field.
3. Add weeks and, inside each week, activities (title, type, category, dates, points) through
   the same editable structure — no separate save step, no page reload.
4. Once the plan is complete, **submit** it for coordination review. Structural fields (week
   and activity details) lock for the author while the plan is `submitted`; narrative content
   stays editable.
5. Whoever holds the review capability (coordination) opens the plan and either **approves**
   it or **requests changes** with a justification. Approving makes the activity visible in the
   course for the first time; requesting changes returns full control to the teacher.
6. After a first approval, editing narrative content never reopens review. Editing a
   structural field (a week's title/duration/period, or an activity's type/category/dates/
   points) immediately regresses the plan to `submitted` so coordination knows a review is
   pending — without hiding the activity or reverting what students/tutors already see.
7. Students and tutors see the resulting **Syllabus** activity once approved, each on their own
   tab: the student tab omits answer keys and tutoring follow-up notes; the tutor tab includes
   them.
8. Site administrators manage the three Custom Fields templates (`plan`, `week`, `activity`)
   under **Site administration > Plugins > Activity modules > Syllabus**.

## Coordinator review notes per field

Beyond the single justification text used when requesting changes, coordination can leave a
note on any specific narrative field (plan, week or activity), right below that field's own
content in the same read view already used to review the plan:

* Type a comment in a field's note box and it autosaves — the teacher sees it as a visible
  indicator right on that field, next to its "View model guidance" toggle.
* Notes stay visible through a resubmission (`submitted` → `changes_requested` → `submitted`
  again), so the teacher can address them without losing track of what was flagged.
* Approving the plan clears every open note — there is nothing left to act on once approved.
* The note box only appears in the read-only review view — a user who also holds the submit
  capability (e.g. testing with one account) never sees it duplicated in their own edit view.

## Resubmission note to coordination

When resubmitting after changes were requested, the teacher gets an optional text box, right
above the "Submit for review" button, to explain what changed:

* Left blank, resubmitting works exactly as before — the note is never required.
* Filled in, it is shown on the plan page to whoever reviews it next, and included in the
  reviewer's notification message.
* It disappears once the plan is approved, the same way the coordinator's own justification
  text does.

## Changed-since-review indicators

A snapshot of the plan's fields — structural columns and narrative Custom Field content alike
— is taken automatically every time coordination approves or requests changes. The next time
coordination opens the plan while a decision is pending, whatever changed since that snapshot
is flagged right where it happened:

* Each narrative field that changed gets a "Changed since last review" badge, right above its
  content — same place the field's own review-note box lives.
* A week or activity with a changed structural detail (dates, duration, type, points, …) gets
  the same badge next to its title; one that did not exist yet gets a "New since last review"
  badge instead.
* A week or activity that existed at the last review but was since deleted has nothing left to
  badge, so it is listed by name instead, in a summary near the top of the page.
* Nothing is flagged on a plan's very first review (there is no prior snapshot yet to compare
  against), and the indicators disappear once coordination decides again — each decision
  re-baselines what "since last review" means.
* Only coordination sees these — never the teacher's own edit view, even for an account that
  also holds the submit capability.

## Which edits reopen review, and which never do

Once a plan has been approved at least once, editing it behaves differently depending on what
changed.

**Always reopens review** — regresses the plan to `submitted` so coordination knows to take
another look, but never hides the activity or reverts what students/tutors already see:

* Editing a week's title, duration, start/end date, synchronous meeting details, or grading
  stage
* Editing an activity's title, type, category, dates, or points
* Deleting a week
* Deleting an activity
* Editing the plan's Final assessment block (title, type, dates, points)

**Never reopens review** — freely editable at any status, including while a review is pending:

* Any narrative field: course description, objectives, contents, methodology, references,
  grading criteria, tutoring follow-up, and the rest of the Custom Fields content
* The Characterisation fields (academic period, course period, total workload) and the
  teacher/course presentation video link
* The number of grading stages and how they combine

**Unpublishing stays available throughout.** The **Unpublish** action (pulling the plan back
to draft and hiding it again) works whenever the activity is currently visible — including
during a reopened-for-review window — not only from a plain `approved` status. It is the only
supported way to hide an already-published plan; toggling the activity's own "Availability"
setting has no effect, since that field is permanently frozen for a Syllabus instance.
