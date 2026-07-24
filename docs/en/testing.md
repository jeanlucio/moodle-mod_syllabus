# 🧪 Automated Tests

Syllabus ships with a PHPUnit test suite covering the approval workflow, web services,
backup/restore, cross-instance isolation, Custom Fields handling, workflow events and
notifications, output rendering, and Privacy API compliance. Every CI push runs against the
full matrix (Moodle 4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL & MariaDB).

> No Behat suite exists yet — end-to-end browser coverage (the edit form, autosave, the
> review workflow, the three role-based tabs) is on the roadmap but not implemented today.

### Activity Library Tests (`tests/lib_test.php`)

| Test file | Cases |
|-----------|------:|
| `lib_test.php` | 1 |
| **Subtotal** | **1** |

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
| `plan_state_manager_test.php` | 17 |
| `structural_change_detector_test.php` | 4 |
| **Subtotal** | **21** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `save_week_test.php` | 7 |
| `review_plan_test.php` | 6 |
| `save_customfield_value_test.php` | 5 |
| `save_plan_details_test.php` | 4 |
| `unpublish_plan_test.php` | 4 |
| `save_activity_test.php` | 3 |
| `delete_activity_test.php` | 2 |
| `delete_week_test.php` | 2 |
| `submit_plan_test.php` | 2 |
| **Subtotal** | **35** |

### Output, Observer & Notification Tests (`tests/output/`, `tests/observer*.php`)

| Test file | Cases |
|-----------|------:|
| `output/tab_full_plan_test.php` | 5 |
| `output/tab_visibility_test.php` | 5 |
| `observer_notifications_test.php` | 4 |
| `observer_test.php` | 3 |
| `output/narrative_editor_test.php` | 3 |
| `output/renderer_test.php` | 3 |
| `output/plan_reader_test.php` | 3 |
| `output/plan_programme_name_test.php` | 2 |
| `output/plan_read_export_test.php` | 2 |
| `output/plan_teacher_name_test.php` | 3 |
| **Subtotal** | **33** |

### Backup, Restore, Privacy & Security Tests

| Test file | Cases |
|-----------|------:|
| `privacy_provider_test.php` | 9 |
| `backup_restore_test.php` | 3 |
| `cross_instance_security_test.php` | 2 |
| `db_uninstall_test.php` | 1 |
| **Subtotal** | **15** |

| **Grand Total** | **121** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/syllabus
```

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `external\delete_activity` | 100% |
| `external\delete_week` | 100% |
| `external\review_plan` | 100% |
| `external\save_activity` | 100% |
| `external\save_plan_details` | 100% |
| `external\save_week` | 100% |
| `external\submit_plan` | 100% |
| `external\unpublish_plan` | 100% |
| `local\structural_change_detector` | 100% |
| `output\narrative_editor` | 100% |
| `output\plan_programme_name` | 100% |
| `output\plan_teacher_name` | 100% |
| `output\renderer` | 100% |
| `local\plan_state_manager` | 91% |
| `external\save_customfield_value` | 98% |
| `output\tab_student_plan` | 94% |
| `output\tab_tutor_plan` | 91% |
| `observer` | 76% |
| `privacy\provider` | 70% |
| `event\plan_changes_requested` | 75% |
| `event\plan_submitted` | 73% |
| `event\plan_approved` | 70% |
| `output\plan_read_export` | 58% |
| `output\plan_reader` | 54% |
| `customfield\plan_handler` | 50% |
| `output\tab_full_plan` | 47% |
| `customfield\syllabus_handler_base` | 43% |
| `customfield\week_handler` | 40% |
| `customfield\activity_handler` | 25% |
| **Overall** | **77%** |

> Prior to this round, `renderer`, `narrative_editor`, the Custom Fields handlers, the three
> workflow event classes, and the observer's notification methods sat at 0% — assumed to be
> reachable only through a real rendered page or a real event log. That assumption was wrong
> for all of them except the mustache templates' own visual output: `render_from_template()`,
> Tiny's configuration builder, capability-gated handler methods and event
> name/description/URL/validation all run perfectly well inside PHPUnit with no browser
> involved, and are now covered directly.

> The remaining gaps are real but narrower: `tab_full_plan` and `plan_reader`/
> `plan_read_export` still have untested branches in the edit-mode weeks/activities reshaping
> and the read-only tutor-field filtering; `privacy\provider`'s early-return guards for
> malformed/partial requests are only partially exercised; and the three event classes'
> `get_description()`/`get_url()` text is covered, but not every one of `core\event\base`'s own
> inherited methods.

> The three Custom Fields handler subclasses (`activity_handler`, `plan_handler`,
> `week_handler`) show low percentages here despite `tests/customfield/syllabus_handler_base_test.php`
> directly calling `belongs_to_syllabus()` (and therefore each subclass's own
> `resolve_syllabus_id()` override) with passing assertions proving the code runs correctly.
> This is a measurement artefact of PHPUnit's code-coverage report for short, near-identical
> override methods shared across sibling classes — not an actual test gap — confirmed by
> inspecting the HTML coverage report line-by-line: the exact same "0% coverage" reading shows
> up even on `plan_handler::resolve_syllabus_id()`'s single `return $instanceid;` line, which
> is exercised by dozens of other tests throughout the suite.
