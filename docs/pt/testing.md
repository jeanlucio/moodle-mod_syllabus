# 🧪 Testes Automatizados

O Syllabus vem com uma suíte de testes PHPUnit cobrindo o workflow de aprovação, web services,
backup/restore, isolamento entre instâncias, e conformidade com a API de Privacidade. Todo push
no CI roda contra a matriz completa (Moodle 4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL e MariaDB).

> Ainda não existe suíte Behat — cobertura de ponta a ponta no navegador (o formulário de
> edição, o autosave, o workflow de revisão, as três abas por papel) está no roadmap, mas não
> foi implementada ainda.

### Testes da Biblioteca da Atividade (`tests/lib_test.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `lib_test.php` | 1 |
| **Subtotal** | **1** |

### Testes de Workflow e Lógica de Negócio (`tests/local/`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `plan_state_manager_test.php` | 17 |
| `structural_change_detector_test.php` | 4 |
| **Subtotal** | **21** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-------------------|------:|
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

### Testes de Output e Observer (`tests/output/`, `tests/observer_test.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `output/tab_visibility_test.php` | 5 |
| `observer_test.php` | 3 |
| **Subtotal** | **8** |

### Testes de Backup, Restore, Privacidade e Segurança

| Arquivo de teste | Casos |
|-------------------|------:|
| `privacy_provider_test.php` | 6 |
| `backup_restore_test.php` | 3 |
| `cross_instance_security_test.php` | 2 |
| `db_uninstall_test.php` | 1 |
| **Subtotal** | **12** |

| **Total geral** | **77** |

```bash
vendor/bin/phpunit --bootstrap lib/phpunit/bootstrap.php mod/syllabus
```

**Cobertura de linhas por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linhas |
|--------|:-------------------:|
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
| **Geral** | **63%** |

> O motor de workflow (`plan_state_manager`), o guard de mudança estrutural, e todo web service
> são exercitados direta e intensamente — é ali que a lógica de aprovação e as checagens de
> isolamento entre instâncias vivem, e a cobertura reflete isso.

> As classes em 0% são as que só rodam dentro de uma página renderizada de verdade ou de uma
> leitura de evento real: `renderer` e `narrative_editor` produzem HTML/configuração do Tiny
> para o navegador; `plan_programme_name`/`plan_teacher_name` são pequenos resolvedores de nome
> de exibição chamados por ramos de renderização de aba que os testes unitários atuais não
> alcançam; as classes de evento `plan_*` são disparadas pelos web services acima, mas seus
> próprios métodos `get_name()`/`get_description()` só são lidos por um log de eventos/backup
> de verdade, nunca por `observer_test.php`, que só verifica o lado do observer; e as
> subclasses de Custom Fields (`activity_handler`, `plan_handler`, `week_handler`) só
> exercitam seus métodos sobrescritos através do próprio fluxo de página da Custom Fields API,
> não por um teste unitário que as isole. Fechar essa lacuna é exatamente para o que a suíte
> Behat planejada serve — nada disso é alcançável a partir do PHPUnit isoladamente como a
> camada de workflow e de web services é.
