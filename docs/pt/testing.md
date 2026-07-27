# 🧪 Testes Automatizados

O Syllabus vem com uma suíte de testes PHPUnit cobrindo o workflow de aprovação, as regras de
completude de submissão, web services, backup/restore, isolamento entre instâncias,
tratamento de Custom Fields e os passos de upgrade que os semeiam/reparam, eventos e
notificações do workflow, renderização de output, e conformidade com a API de Privacidade —
mais uma suíte Behat provando o mesmo workflow de ponta a ponta, com sessão real de professor,
coordenação, tutor e estudante. Todo push no CI roda contra a matriz completa (Moodle 4.5 →
5.2, PHP 8.2 → 8.4, PostgreSQL e MariaDB).

### Behat (`tests/behat/mod_syllabus_workflow.feature`)

Três cenários compartilham um Background que leva um plano de rascunho a aprovado, e então
verificam o que só uma sessão de navegador real, com múltiplos usuários, consegue provar:

* um estudante alcança o plano aprovado na sua própria aba, sem nenhuma barra de abas;
* um tutor alcança com barra de abas, abrindo direto na aba "Tutor plan";
* uma edição estrutural pós-aprovação regride o plano para submetido sem escondê-lo de
  tutores e estudantes, e com Despublicar continuando alcançável — o status do plano nunca é
  a mesma coisa que "isso está visível agora", e só um carregamento de página real via
  `view.php`, como cada papel, realmente prova que os dois ficam sincronizados.

### Testes da Biblioteca da Atividade (`tests/lib_test.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `lib_test.php` | 4 |
| **Subtotal** | **4** |

### Testes dos Handlers de Custom Fields (`tests/customfield/`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `syllabus_handler_base_test.php` | 9 |
| **Subtotal** | **9** |

### Testes dos Eventos do Workflow (`tests/event/`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `plan_changes_requested_test.php` | 3 |
| `plan_approved_test.php` | 2 |
| `plan_submitted_test.php` | 2 |
| **Subtotal** | **7** |

### Testes de Workflow e Lógica de Negócio (`tests/local/`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `plan_state_manager_test.php` | 19 |
| `plan_completeness_checker_test.php` | 12 |
| `structural_change_detector_test.php` | 4 |
| **Subtotal** | **35** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-------------------|------:|
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

### Testes de Output, Observer e Notificações (`tests/output/`, `tests/observer*.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
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

### Testes de Backup, Restore, Upgrade, Privacidade e Segurança

| Arquivo de teste | Casos |
|-------------------|------:|
| `privacy_provider_test.php` | 9 |
| `db_upgrade_test.php` | 10 |
| `backup_restore_test.php` | 3 |
| `cross_instance_security_test.php` | 2 |
| `db_uninstall_test.php` | 1 |
| **Subtotal** | **25** |

| **Total geral** | **164** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/syllabus
```

**Cobertura de linhas por classe (PHPUnit + Xdebug, via a ferramenta `moodle-coverage`):**

| Classe | Cobertura de linhas |
|--------|:-------------------:|
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
| **Geral** | **98%** |

> O punhado de classes ainda abaixo de 100% cobre lacunas genuínas e estreitas, revisadas
> individualmente e deixadas sem teste por serem de baixo valor, não por terem passado
> despercebidas — wrappers triviais de `create()` nos quatro handlers de Custom Field, um ramo
> de `save_customfield_value::execute()`, alguns guards de retorno antecipado em
> `observer`/`privacy\provider`, e `help_handler::resolve_syllabus_id()` (a área `help` não tem
> valores por instância, então esse método de uma linha nunca é chamado de verdade).
