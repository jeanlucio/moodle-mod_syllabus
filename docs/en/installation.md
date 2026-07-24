# 🛠️ Installation & Configuration

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `syllabus` (if necessary).
   Final path:
   `your-moodle/mod/syllabus/`
4. Visit **Site administration > Notifications** to complete installation. This also seeds the
   default Custom Fields for the `plan`, `week` and `activity` areas, translated into whatever
   language the installing administrator is using.
5. Assign the review capability (`mod/syllabus:review`) to whichever role represents
   coordination at your institution — typically via a role added at the course category level —
   and the tutor view capability (`mod/syllabus:viewtutorview`) to your tutor role.
6. Add a **Syllabus** activity to a course.

The narrative Custom Fields can be reviewed or extended per area at
**Site administration > Plugins > Activity modules > Syllabus**, under **Manage plan/week/activity
fields**, as covered in the [Usage](#usage) section below.
