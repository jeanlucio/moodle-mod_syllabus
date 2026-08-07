# Moodle Activity Syllabus

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat)
![Status](https://img.shields.io/badge/Status-Alpha-red?style=flat)
[![Latest Release](https://img.shields.io/github/v/release/jeanlucio/moodle-mod_syllabus?style=flat)](https://github.com/jeanlucio/moodle-mod_syllabus/releases)
[![Author](https://img.shields.io/badge/by-Jean_Lucio-6f42c1?style=flat)](https://marketplace.moodle.com/user/984)

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_syllabus/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_syllabus/actions/workflows/ci.yml)
[![Last Commit](https://img.shields.io/github/last-commit/jeanlucio/moodle-mod_syllabus?style=flat)](https://github.com/jeanlucio/moodle-mod_syllabus/commits)
[![Open Issues](https://img.shields.io/github/issues/jeanlucio/moodle-mod_syllabus?style=flat)](https://github.com/jeanlucio/moodle-mod_syllabus/issues)

[English](#english) | [Português](#português)

---

## English

**Syllabus** is a Moodle activity that lets a teacher fill in a single structured course plan
— course description, objectives, contents, methodology, references, grading criteria, weeks
and activities — with per-field autosave, instead of three separate, easily-diverging documents.

The teacher submits the plan for coordination approval. Once approved, the same instance
automatically becomes visible in the course and renders three role-specific views from the
same stored data: coordination/teacher (full plan, with review actions), tutor (with grading
criteria and answer keys), and student (without them) — never three separate files to keep
in sync.

📚 **[Full documentation](https://jeanlucio.github.io/moodle-mod_syllabus/)** — features,
educational purpose, usage guide, the full test suite, and security details.

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5 – 5.2 |
| PHP       | 8.2+    |

### 🛠️ Installation & Configuration

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `syllabus` (if necessary).
   Final path:
   `your-moodle/mod/syllabus/`
4. Visit **Site administration > Notifications** to complete installation.
5. Assign `mod/syllabus:review` to your coordination role and `mod/syllabus:viewtutorview` to
   your tutor role, then add a **Syllabus** activity to a course.

Narrative content fields (ementa, objectives, contents, methodology, references, grading
criteria and more) are managed as Custom Fields per area (`plan`, `week`, `activity`) at
**Site administration > Plugins > Activity modules > Syllabus**, as covered in the
[Usage](https://jeanlucio.github.io/moodle-mod_syllabus/#usage) section of the full
documentation.

### 🆘 Support

Found a bug or have a question? Open an issue on the
[issue tracker](https://github.com/jeanlucio/moodle-mod_syllabus/issues).

### 📄 License

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Maintainer

Maintained by [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Back to top](#english)

---

## Português

O **Syllabus** é uma atividade Moodle onde o professor preenche um único plano de disciplina
estruturado — ementa, objetivos, conteúdos, metodologia, referências, critérios de avaliação,
aulas e atividades — com autosave por campo, em vez de três documentos separados e facilmente
divergentes.

O professor submete o plano para aprovação da coordenação. Uma vez aprovado, a mesma instância
se torna automaticamente visível no curso e passa a renderizar três visões por papel a partir
dos mesmos dados armazenados: coordenação/professor (plano completo, com ações de revisão),
tutor (com critérios de avaliação e gabarito), e estudante (sem eles) — nunca três arquivos
separados para manter sincronizados.

📚 **[Documentação completa](https://jeanlucio.github.io/moodle-mod_syllabus/pt.html)** —
funcionalidades, finalidade educacional, guia de uso, a suíte completa de testes, e detalhes de
segurança.

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5 – 5.2 |
| PHP        | 8.2+   |

### 🛠️ Instalação e Configuração

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `syllabus` (se necessário).
   Caminho final:
   `seu-moodle/mod/syllabus/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Atribua `mod/syllabus:review` ao seu papel de coordenação e `mod/syllabus:viewtutorview` ao
   seu papel de tutor, depois adicione uma atividade **Syllabus** a um curso.

Os campos de conteúdo narrativo (ementa, objetivos, conteúdos, metodologia, referências,
critérios de avaliação e mais) são gerenciados como Custom Fields por área (`plan`, `week`,
`activity`) em **Administração do site > Plugins > Módulos de atividade > Syllabus**, conforme
explicado na seção [Como Usar](https://jeanlucio.github.io/moodle-mod_syllabus/pt.html#usage)
da documentação completa.

### 🆘 Suporte

Encontrou um bug ou tem alguma dúvida? Abra uma issue no
[rastreador de issues](https://github.com/jeanlucio/moodle-mod_syllabus/issues).

### 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Mantenedor

Mantido por [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Voltar ao topo](#português)
