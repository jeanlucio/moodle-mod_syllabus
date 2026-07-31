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
note on any specific narrative field (plan, week or activity) from a dedicated panel in the
Full plan tab:

* Open a field's note row, type a comment, and it autosaves — the teacher sees it as a visible
  indicator right on that field, next to its "View model guidance" toggle.
* Notes stay visible through a resubmission (`submitted` → `changes_requested` → `submitted`
  again), so the teacher can address them without losing track of what was flagged.
* Approving the plan clears every open note — there is nothing left to act on once approved.

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
