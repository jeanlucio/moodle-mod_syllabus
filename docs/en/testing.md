# 🧪 Automated Tests

Syllabus ships with a PHPUnit test suite covering the approval workflow, web services,
backup/restore, cross-instance isolation, and Privacy API compliance. Every CI push runs
against the full matrix (Moodle 4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL & MariaDB).

> No Behat suite exists yet — end-to-end browser coverage (the edit form, autosave, the
> review workflow, the three role-based tabs) is on the roadmap but not implemented today.

### Activity Library Tests (`tests/lib_test.php`)

| Test file | Cases |
|-----------|------:|
| `lib_test.php` | 1 |
| **Subtotal** | **1** |

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

### Output & Observer Tests (`tests/output/`, `tests/observer_test.php`)

| Test file | Cases |
|-----------|------:|
| `output/tab_visibility_test.php` | 5 |
| `observer_test.php` | 3 |
| **Subtotal** | **8** |

### Backup, Restore, Privacy & Security Tests

| Test file | Cases |
|-----------|------:|
| `privacy_provider_test.php` | 6 |
| `backup_restore_test.php` | 3 |
| `cross_instance_security_test.php` | 2 |
| `db_uninstall_test.php` | 1 |
| **Subtotal** | **12** |

| **Grand Total** | **77** |

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
| `local\plan_state_manager` | 91% |
| `external\save_customfield_value` | 98% |
| `output\tab_student_plan` | 94% |
| `output\tab_tutor_plan` | 91% |
| `privacy\provider` | 63% |
| `output\tab_full_plan` | 46% |
| `output\plan_read_export` | 44% |
| `observer` | 19% |
| `output\plan_reader` | 19% |
| `customfield\syllabus_handler_base` | 3% |
| `customfield\activity_handler` | 0% |
| `customfield\plan_handler` | 0% |
| `customfield\week_handler` | 0% |
| `event\plan_approved` | 0% |
| `event\plan_changes_requested` | 0% |
| `event\plan_submitted` | 0% |
| `output\narrative_editor` | 0% |
| `output\plan_programme_name` | 0% |
| `output\plan_teacher_name` | 0% |
| `output\renderer` | 0% |
| **Overall** | **63%** |

> The workflow engine (`plan_state_manager`), the structural-change guard, and every web
> service are directly and heavily exercised — this is where the approval logic and the
> instance-isolation checks live, and coverage reflects that.

> The classes at 0% are the ones that only run inside a real rendered page or a real event
> read: `renderer` and `narrative_editor` produce HTML/Tiny configuration for the browser;
> `plan_programme_name`/`plan_teacher_name` are small display-name resolvers invoked from
> tab rendering branches the current unit tests don't reach; the `plan_*` event classes are
> triggered by the web services above but their own `get_name()`/`get_description()` methods
> are only read by a real event log/backup, never by `observer_test.php`, which only asserts
> on the observer side; and the Custom Fields subclasses (`activity_handler`, `plan_handler`,
> `week_handler`) only exercise their overridden methods through the Custom Fields API's own
> page flow, not through a unit test that isolates them. Closing this gap is exactly what the
> planned Behat suite is for — none of it is reachable from PHPUnit in isolation the way the
> workflow and web-service layers are.
