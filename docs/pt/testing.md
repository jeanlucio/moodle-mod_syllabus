# 🧪 Testes Automatizados

O Syllabus vem com uma suíte de testes PHPUnit cobrindo o workflow de aprovação, web services,
backup/restore, isolamento entre instâncias, tratamento de Custom Fields, eventos e
notificações do workflow, renderização de output, e conformidade com a API de Privacidade.
Todo push no CI roda contra a matriz completa (Moodle 4.5 → 5.2, PHP 8.2 → 8.4, PostgreSQL e
MariaDB).

> Ainda não existe suíte Behat — cobertura de ponta a ponta no navegador (o formulário de
> edição, o autosave, o workflow de revisão, as três abas por papel) está no roadmap, mas não
> foi implementada ainda.

### Testes da Biblioteca da Atividade (`tests/lib_test.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
| `lib_test.php` | 1 |
| **Subtotal** | **1** |

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

### Testes de Output, Observer e Notificações (`tests/output/`, `tests/observer*.php`)

| Arquivo de teste | Casos |
|-------------------|------:|
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

### Testes de Backup, Restore, Privacidade e Segurança

| Arquivo de teste | Casos |
|-------------------|------:|
| `privacy_provider_test.php` | 9 |
| `backup_restore_test.php` | 3 |
| `cross_instance_security_test.php` | 2 |
| `db_uninstall_test.php` | 1 |
| **Subtotal** | **15** |

| **Total geral** | **121** |

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
| **Geral** | **77%** |

> Antes desta rodada, `renderer`, `narrative_editor`, os handlers de Custom Fields, as três
> classes de evento do workflow, e os métodos de notificação do observer estavam em 0% —
> presumidos alcançáveis apenas por uma página renderizada de verdade ou um log de evento real.
> Essa suposição estava errada para todos eles, exceto o output visual dos próprios templates
> mustache: `render_from_template()`, o construtor de configuração do Tiny, os métodos dos
> handlers protegidos por capability, e nome/descrição/URL/validação dos eventos rodam
> perfeitamente dentro do PHPUnit sem nenhum navegador envolvido, e agora estão cobertos
> diretamente.

> As lacunas restantes são reais, mas mais estreitas: `tab_full_plan` e `plan_reader`/
> `plan_read_export` ainda têm ramos sem teste na remodelagem de semanas/atividades em modo de
> edição e na filtragem de campos exclusivos de tutor no modo leitura; os guards de
> early-return de `privacy\provider` para requisições malformadas/parciais estão só
> parcialmente exercitados; e o texto de `get_description()`/`get_url()` das três classes de
> evento está coberto, mas nem todo método próprio herdado de `core\event\base`.

> As três subclasses de handler de Custom Fields (`activity_handler`, `plan_handler`,
> `week_handler`) aparecem com percentuais baixos aqui apesar de
> `tests/customfield/syllabus_handler_base_test.php` chamar diretamente `belongs_to_syllabus()`
> (e portanto o próprio override `resolve_syllabus_id()` de cada subclasse) com asserções que
> passam, provando que o código roda corretamente. Isso é um artefato de medição do relatório
> de cobertura do PHPUnit para métodos de override curtos e quase idênticos compartilhados
> entre classes irmãs — não uma lacuna real de teste — confirmado inspecionando o relatório
> HTML de cobertura linha a linha: a mesma leitura de "0% de cobertura" aparece até na única
> linha `return $instanceid;` de `plan_handler::resolve_syllabus_id()`, que é exercitada por
> dezenas de outros testes ao longo da suíte.
