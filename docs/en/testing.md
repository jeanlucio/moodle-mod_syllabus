# 🧪 Automated Tests

Syllabus ships with a PHPUnit test suite covering the approval workflow, submission
completeness rules, web services, backup/restore, cross-instance isolation, Custom Fields
handling and the upgrade steps that seed/repair them, workflow events and notifications,
output rendering, and Privacy API compliance — plus a Behat suite proving the same workflow
end to end, across a real teacher, coordinator, tutor and student session. Every CI push runs
against the full matrix (Moodle 4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL & MariaDB).

### Behat (`tests/behat/mod_syllabus_workflow.feature`)

Three scenarios share a Background that takes a plan from draft through submitted to
approved, then verify what only a real, multi-user browser session can prove:

* a student reaches the approved plan on their own tab, with no tab bar at all;
* a tutor reaches it with a tab bar and opens directly on the Tutor plan tab;
* a structural edit after approval regresses the plan to submitted without hiding it from
  tutors and students, and with Unpublish still reachable — the plan's own status is never
  the same thing as "is this currently visible", and only a real page load through
  `view.php` as each role actually proves the two stay in sync.

### Activity Library Tests (`tests/lib_test.php`)

| Test file | Cases |
|-----------|------:|
| `lib_test.php` | 4 |
| **Subtotal** | **4** |

### Custom Fields Handler Tests (`tests/customfield/`)

| Test file | Cases |
|-----------|------:|
| `syllabus_handler_base_test.php` | 9 |
| **Subtotal** | **9** |

### Workflow Event Tests (`tests/event/`)

| Test file | Cases |
|-----------|------:|
| `plan_changes_requested_test.php` | 3 |
| `plan_approved_test.php` | 2 |
| `plan_submitted_test.php` | 2 |
| **Subtotal** | **7** |

### Workflow & Business-Logic Tests (`tests/local/`)

| Test file | Cases |
|-----------|------:|
| `plan_state_manager_test.php` | 19 |
| `plan_completeness_checker_test.php` | 12 |
| `structural_change_detector_test.php` | 4 |
| **Subtotal** | **35** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `save_week_test.php` | 9 |
| `review_plan_test.php` | 6 |
| `save_plan_details_test.php` | 6 |
| `save_customfield_value_test.php` | 5 |
| `save_final_assessment_test.php` | 4 |
| `unpublish_plan_test.php` | 4 |
| `save_activity_test.php` | 3 |
| `reset_field_description_test.php` | 3 |
| `delete_activity_test.php` | 2 |
| `delete_week_test.php` | 2 |
| `submit_plan_test.php` | 2 |
| **Subtotal** | **46** |

### Output, Observer & Notification Tests (`tests/output/`, `tests/observer*.php`)

| Test file | Cases |
|-----------|------:|
| `output/tab_full_plan_test.php` | 8 |
| `output/tab_visibility_test.php` | 5 |
| `observer_notifications_test.php` | 5 |
| `output/plan_reader_test.php` | 4 |
| `observer_test.php` | 3 |
| `output/narrative_editor_test.php` | 3 |
| `output/plan_teacher_name_test.php` | 3 |
| `output/renderer_test.php` | 3 |
| `output/plan_programme_name_test.php` | 2 |
| `output/plan_read_export_test.php` | 2 |
| **Subtotal** | **38** |

### Backup, Restore, Upgrade, Privacy & Security Tests

| Test file | Cases |
|-----------|------:|
| `privacy_provider_test.php` | 9 |
| `db_upgrade_test.php` | 10 |
| `backup_restore_test.php` | 3 |
| `cross_instance_security_test.php` | 2 |
| `db_uninstall_test.php` | 1 |
| **Subtotal** | **25** |

| **Grand Total** | **164** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/syllabus
```

**Line coverage by class (PHPUnit + Xdebug, via the `moodle-coverage` tool):**

| Class | Line coverage |
|-------|:-------------:|
| `event\plan_approved` | 100% |
| `event\plan_changes_requested` | 100% |
| `event\plan_submitted` | 100% |
| `external\delete_activity` | 100% |
| `external\delete_week` | 100% |
| `external\reset_field_description` | 100% |
| `external\review_plan` | 100% |
| `external\save_activity` | 100% |
| `external\save_final_assessment` | 100% |
| `external\save_plan_details` | 100% |
| `external\save_week` | 100% |
| `external\submit_plan` | 100% |
| `external\unpublish_plan` | 100% |
| `local\customfield_seeder` | 100% |
| `local\help_text_builder` | 100% |
| `local\plan_completeness_checker` | 100% |
| `local\plan_state_manager` | 100% |
| `local\structural_change_detector` | 100% |
| `output\narrative_editor` | 100% |
| `output\plan_programme_name` | 100% |
| `output\plan_read_export` | 100% |
| `output\plan_teacher_name` | 100% |
| `output\renderer` | 100% |
| `output\tab_full_plan` | 100% |
| `output\tab_student_plan` | 100% |
| `output\tab_tutor_plan` | 100% |
| `customfield\syllabus_handler_base` | 97% |
| `output\plan_reader` | 96% |
| `external\save_customfield_value` | 98% |
| `observer` | 94% |
| `privacy\provider` | 89% |
| `customfield\activity_handler` | 88% |
| `customfield\week_handler` | 80% |
| `customfield\plan_handler` | 75% |
| `customfield\help_handler` | 50% |
| **Overall** | **98%** |

> **A note on how these numbers were reached.** For most of this plugin's development, doc-
> comment `@covers` annotations were written per test method (`@coversDefaultClass \fullclass`
> plus `@covers ::method` on each test, or a repeated `@covers \fullclass::method`) — valid
> PHPUnit syntax that reads as reasonable on its own. That style silently discards coverage
> credit for anything a test exercises *besides* the one annotated method in the same run — a
> private helper, a sibling method, a class reached only through dispatch. At one point this
> looked like a measurement limit intrinsic to PHPUnit for short override methods shared
> across sibling classes; it was not. Switching every test class to a single class-level
> `@covers \fullclass` (no `::method`, one or more targets per class) recovered real,
> already-passing coverage that had simply never been attributed: overall line coverage moved
> from roughly 75% to 98% purely from that annotation change, with no new test logic involved.
> The handful of classes still below 100% here are genuine, narrow gaps — trivial `create()`
> wrappers on the four Custom Field handlers, one branch of `save_customfield_value::execute()`,
> a couple of early-return guards in `observer`/`privacy\provider`, and
> `help_handler::resolve_syllabus_id()` (the `help` area has no per-instance values, so that
> one-line method is never actually called) — reviewed individually and left untested as
> low-value, not overlooked.
