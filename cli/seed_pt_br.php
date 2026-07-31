<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Seed script for manual testing of mod_syllabus (PT-BR).
 *
 * Creates a demo course with a single, fully populated Syllabus activity reproducing the
 * "Teorias, Metodologias e Planejamento Pedagógico em EaD" course plan and tutoring plan
 * supplied by the plugin author: characterisation, four weeks with their five activities,
 * every narrative Custom Field (plan/week/activity areas) and the final assessment block.
 * The plan is left in 'draft' status, fully populated, so the workflow itself (submit as the
 * teacher, then approve/request changes — including leaving per-field review notes — as the
 * coordinator) can be exercised manually instead of already landing pre-approved. Run with
 * --reset to wipe and recreate everything.
 *
 * Usage:
 *   php mod/syllabus/cli/seed_pt_br.php --password=SuaSenhaDev
 *   php mod/syllabus/cli/seed_pt_br.php --password=SuaSenhaDev --reset
 *   php mod/syllabus/cli/seed_pt_br.php --password=SuaSenhaDev --force
 *
 * O parâmetro --password é obrigatório e define a senha de login de todos os
 * usuários demo criados pelo script (seed_syllabus_teacher, seed_syllabus_coordinator, …).
 * Use-a para entrar no Moodle como um desses usuários de teste.
 * O script recusa execução em sites que não sejam de desenvolvimento, a menos
 * que --force seja informado (use em domínios de dev personalizados).
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');

// Suppress email sending in dev/test environments (no sendmail in Docker).
$CFG->noemailever = true;

[$options, $unrecognised] = cli_get_params(
    ['reset' => false, 'help' => false, 'password' => '', 'force' => false],
    ['h' => 'help', 'r' => 'reset', 'p' => 'password', 'f' => 'force']
);

if ($options['help']) {
    cli_writeln("Seed script for mod_syllabus manual testing.\n");
    cli_writeln("Options:");
    cli_writeln("  --password=<pass>  Required. Password for all seed users.");
    cli_writeln("  --reset            Wipe the demo course and recreate everything from scratch.");
    cli_writeln("  --force            Skip the development-site guard (use on custom dev domains).");
    cli_writeln("  --help             Show this message.");
    exit(0);
}

// Refuse to run without an explicit password to avoid known-credential accounts in production.
if (empty($options['password'])) {
    cli_error("ERROR: --password=<pass> is required. Aborting to prevent known-credential accounts.");
}

// Guard: refuse to run if this looks like a production environment.
// Use --force to bypass when running on a custom development domain.
if (
    !$options['force'] && !empty($CFG->wwwroot)
    && !preg_match('/localhost|127\.0\.0\.1|\.local(:|\/|$)|\.test(:|\/|$)/i', $CFG->wwwroot)
) {
    cli_error(
        "ERROR: This script must not be run on a non-development site ({$CFG->wwwroot}).\n" .
        "If this is intentional, re-run with --force."
    );
}

/** @var string Shortname of the demo course created by this seed script. */
const SEED_COURSE_SHORTNAME = 'syllabus-demo-ptbr';

/** @var string Password for all seed users, supplied via --password flag. */
define('SEED_PASSWORD', $options['password']);

cli_writeln("=== mod_syllabus seed ===\n");

// 1. Reset.
if ($options['reset']) {
    $existing = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
    if ($existing) {
        cli_writeln("Removendo curso demo existente (id={$existing->id})...");
        delete_course($existing, false);
        cli_writeln("Curso removido.\n");
    }
    // Remove seed users.
    $seedusers = $DB->get_records_sql(
        "SELECT * FROM {user} WHERE username LIKE 'seed_syllabus_%' AND deleted = 0"
    );
    foreach ($seedusers as $u) {
        delete_user($u);
    }
    cli_writeln("Usuários seed removidos.\n");
}

// 2. Course.
$course = $DB->get_record('course', ['shortname' => SEED_COURSE_SHORTNAME]);
if ($course) {
    cli_writeln("Curso demo já existe (id={$course->id}). Use --reset para recriar.\n");
} else {
    $coursedata = (object) [
        'fullname'    => 'Teorias, Metodologias e Planejamento Pedagógico em EaD (Demo PT-BR)',
        'shortname'   => SEED_COURSE_SHORTNAME,
        'summary'     => 'Curso criado automaticamente pelo seed do mod_syllabus para testes manuais.',
        'format'      => 'topics',
        'numsections' => 1,
        'visible'     => 1,
        'category'    => 1,
    ];
    $course = create_course($coursedata);
    cli_writeln("Curso criado: id={$course->id}");
}

$coursecontext = context_course::instance($course->id);

// 3. Users.

/**
 * Creates a user if it does not already exist.
 *
 * @param string $username Username.
 * @param string $firstname First name.
 * @param string $lastname Last name.
 * @param string $password Plaintext password.
 * @return stdClass User record.
 */
function seed_create_user(string $username, string $firstname, string $lastname, string $password): stdClass {
    global $DB, $CFG;

    $existing = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
    if ($existing) {
        return $existing;
    }

    $user = (object) [
        'auth'         => 'manual',
        'confirmed'    => 1,
        'policyagreed' => 1,
        'deleted'      => 0,
        'mnethostid'   => $CFG->mnet_localhost_id,
        'username'     => $username,
        'password'     => hash_internal_user_password($password),
        'firstname'    => $firstname,
        'lastname'     => $lastname,
        'email'        => $username . '@syllabus.test',
        'lang'         => 'pt_br',
        'timezone'     => '99',
        'picture'      => 0,
        'timecreated'  => time(),
        'timemodified' => time(),
    ];

    $user->id = $DB->insert_record('user', $user);
    return $user;
}

$teacher = seed_create_user('seed_syllabus_teacher', 'Jean', 'Lúcio', SEED_PASSWORD);
$coordinator = seed_create_user('seed_syllabus_coordinator', 'Beatriz', 'Nascimento', SEED_PASSWORD);
$tutor = seed_create_user('seed_syllabus_tutor', 'Marina', 'Tavares', SEED_PASSWORD);
$students = [
    seed_create_user('seed_syllabus_aline', 'Aline', 'Ferreira', SEED_PASSWORD),
    seed_create_user('seed_syllabus_bruno', 'Bruno', 'Carvalho', SEED_PASSWORD),
];
cli_writeln(
    "Usuários criados/encontrados: 1 professor(a) autor(a), 1 coordenador(a), 1 tutor(a), "
    . count($students) . ' estudante(s).'
);

// 4. Enrolment.
$enrol = enrol_get_plugin('manual');
$enrolinstance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
if (!$enrolinstance) {
    $enrolinstanceid = $enrol->add_default_instance($course);
    $enrolinstance = $DB->get_record('enrol', ['id' => $enrolinstanceid]);
}

$editingteacherrole = $DB->get_record('role', ['shortname' => 'editingteacher'], '*', MUST_EXIST);
$managerrole = $DB->get_record('role', ['shortname' => 'manager'], '*', MUST_EXIST);
$teacherrole = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
$studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);

$enrol->enrol_user($enrolinstance, $teacher->id, $editingteacherrole->id);
$enrol->enrol_user($enrolinstance, $coordinator->id, $managerrole->id);
$enrol->enrol_user($enrolinstance, $tutor->id, $teacherrole->id);
foreach ($students as $student) {
    $enrol->enrol_user($enrolinstance, $student->id, $studentrole->id);
}
cli_writeln("Matrículas concluídas.");

// 5. Narrative content helper.

/**
 * Wraps a list of strings in an HTML unordered list.
 *
 * @param string[] $items List item texts (already HTML-safe).
 * @return string
 */
function seed_ul(array $items): string {
    return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
}

/**
 * Wraps a list of strings in an HTML ordered list.
 *
 * @param string[] $items List item texts (already HTML-safe).
 * @return string
 */
function seed_ol(array $items): string {
    return '<ol><li>' . implode('</li><li>', $items) . '</li></ol>';
}

/**
 * Sets the value of one narrative Custom Field textarea, mirroring
 * mod_syllabus\external\save_customfield_value::execute() without the web service
 * capability/context boilerplate that only applies to a live authenticated request.
 *
 * @param string $handlerclass Short class name under mod_syllabus\customfield: plan_handler,
 *        week_handler or activity_handler.
 * @param int $instanceid Syllabus/week/activity ID owning the value.
 * @param string $shortname Custom field shortname, see customfield_seeder::areas().
 * @param string $html HTML content to store.
 * @return void
 */
function seed_set_customfield(string $handlerclass, int $instanceid, string $shortname, string $html): void {
    $classname = "mod_syllabus\\customfield\\{$handlerclass}";
    $handler = $classname::create();
    $fielddatas = $handler->get_instance_data($instanceid, true);

    foreach ($fielddatas as $datacontroller) {
        if ($datacontroller->get_field()->get('shortname') !== $shortname) {
            continue;
        }
        if (!$datacontroller->get('id')) {
            $datacontroller->set('contextid', $handler->get_instance_context($instanceid)->id);
        }
        $fakeform = new stdClass();
        $fakeform->{$datacontroller->get_form_element_name()} = [
            'text'   => $html,
            'format' => FORMAT_HTML,
            'itemid' => 0,
        ];
        $datacontroller->instance_form_save($fakeform);
        return;
    }
}

// 6. Syllabus activity (structural fields).

$existingsyllabus = $DB->get_record('syllabus', ['course' => $course->id]);
if ($existingsyllabus) {
    $syllabusid = (int) $existingsyllabus->id;
    $cm = get_coursemodule_from_instance('syllabus', $syllabusid, $course->id, false, MUST_EXIST);
    cli_writeln("Plano de Disciplina já existe: id={$syllabusid}.\n");
} else {
    $moduleid = $DB->get_field('modules', 'id', ['name' => 'syllabus'], MUST_EXIST);

    $moduleinfo = (object) [
        'modulename'               => 'syllabus',
        'module'                   => $moduleid,
        'course'                   => $course->id,
        'section'                  => 1,
        'visible'                  => 1,
        'name'                     => 'Teorias, Metodologias e Planejamento Pedagógico em EaD',
        'intro'                    => 'Plano de disciplina do curso Educação a Distância na EPT.',
        'introformat'              => FORMAT_HTML,
        'completion'               => 0,
        'academicperiod'           => '2026.1',
        'coursestartdate'          => mktime(0, 0, 0, 5, 7, 2026),
        'courseenddate'            => mktime(23, 59, 59, 6, 10, 2026),
        'totalduration'            => 30,
        'presentationvideourl'     => 'https://youtu.be/HLCiwEGeNp4',
        'stagecount'               => 1,
        'grademethod'              => 'sum',
        'finalassessmenttitle'     => 'Avaliação Final — Teorias, Metodologias e Planejamento Pedagógico em EaD',
        'finalassessmenttype'      => 'Questionário',
        'finalassessmentstartdate' => mktime(0, 0, 0, 6, 11, 2026),
        'finalassessmentenddate'   => mktime(23, 59, 59, 6, 15, 2026),
        'finalassessmentpoints'    => 100,
    ];

    // The add_moduleinfo() call requires a logged-in user context; called directly (not
    // through the real mod_form), so no form validation runs — mirrors block_playerhud's
    // seed pattern.
    $origuser = $USER;
    \core\session\manager::set_user(get_admin());
    $moduleinfo = add_moduleinfo($moduleinfo, $course, null);
    \core\session\manager::set_user($origuser);

    $syllabusid = (int) $moduleinfo->instance;
    $cm = get_coursemodule_from_id('syllabus', $moduleinfo->coursemodule, 0, false, MUST_EXIST);
    cli_writeln("Plano de Disciplina criado: id={$syllabusid}.");
}

// 7. Plan-level narrative Custom Fields.

$ementa = '<p>Teorias pedagógicas e estilos de aprendizagem em EaD. Aprendizagem de pessoas adultas e '
    . 'formação para o mundo do trabalho. Planejamento pedagógico para a EPT na modalidade a distância, com '
    . 'ênfase nas práticas pedagógicas para o desenvolvimento de saberes profissionais e tecnológicos.</p>';

$objetivos = '<p>Conhecer e analisar teorias pedagógicas que fundamentam práticas e metodologias de '
    . 'ensino-aprendizagem na modalidade a distância. Compreender e planejar conteúdos técnicos e '
    . 'tecnológicos para a EPT na modalidade de EaD, sob a perspectiva da formação integral, emancipatória '
    . 'e comprometida com a transformação social.</p>';

$conteudos = seed_ul([
    'Semana 1 - Teorias pedagógicas e estilos de aprendizagem na Educação a Distância',
    'Semana 2 - Abordagens da Psicologia da Aprendizagem e da Educação na EaD e EPT',
    'Semana 3 - Planejamento pedagógico na EaD para a EPT: design instrucional',
    'Semana 4 - Metodologias de ensino-aprendizagem na EaD',
]);

$metodologia = '<p>Olá, caro(a) estudante! Seja muito bem-vindo(a) à disciplina de Teorias, Metodologias e '
    . 'Planejamento Pedagógico em EaD. Nossa proposta pedagógica foi desenhada para ir além da simples '
    . 'transmissão de conteúdo; o objetivo é construir um ambiente de aprendizado ativo, crítico e '
    . 'colaborativo, no qual o(a) estudante é o(a) protagonista.</p>'
    . '<p><strong>Conectando a ementa aos objetivos</strong>: a ementa define os grandes temas que '
    . 'exploraremos; para trabalhá-la de forma prática e aprofundada, cada semana foi planejada como uma '
    . 'etapa para alcançar os objetivos de aprendizagem — nenhuma atividade existe por acaso.</p>'
    . '<p><strong>Estratégias de aprendizagem</strong>, articuladas em momentos síncronos e assíncronos:</p>'
    . seed_ol([
        'Encontros síncronos (14/05/2026 e 27/05/2026): debate, aprofundamento e construção coletiva '
            . 'de conhecimento, mediados pela docente via plataforma virtual;',
        'Atividades assíncronas: leitura do material digital, ebooks, videoaulas e materiais '
            . 'complementares disponibilizados no AVA, no ritmo próprio do(a) estudante;',
        'Fóruns avaliativos semanais: a cada semana, um fórum avaliativo valendo 15 pontos propõe '
            . 'reflexão e debate sobre o conteúdo do capítulo correspondente;',
        'Questionário final (Semana 4): avaliação objetiva contemplando todos os capítulos, valendo '
            . '40 pontos.',
    ])
    . '<p><strong>Sistema de pontuação</strong></p>'
    . seed_ul([
        '4 fóruns semanais (4 × 15 pontos) = 60 pontos',
        'Questionário final (Semana 4) = 40 pontos',
        'Total = 100 pontos',
    ]);

$roteirovideo = '<p>Vídeo de apresentação da disciplina e do(a) professor(a): '
    . 'https://youtu.be/HLCiwEGeNp4</p>'
    . '<p><strong>Bloco 1 — Abertura e boas-vindas</strong>: saudação inicial, apresentação do(a) '
    . 'professor(a) e boas-vindas à turma.</p>'
    . '<p><strong>Bloco 2 — Objetivos e relevância</strong>: a disciplina desenvolve a capacidade de '
    . 'compreender e aplicar teorias, metodologias e estratégias de planejamento pedagógico na EaD, com '
    . 'olhar crítico para a EPT.</p>'
    . '<p><strong>Bloco 3 — Metodologia</strong>: quatro semanas, cada uma correspondendo a um capítulo '
    . 'do material digital, com dois encontros síncronos ao vivo, fóruns semanais e um questionário '
    . 'final.</p>'
    . '<p><strong>Bloco 4 — Avaliação</strong>: avaliação contínua e formativa — quatro fóruns semanais '
    . '(15 pontos cada) e um questionário final (40 pontos), totalizando 100 pontos.</p>'
    . '<p><strong>Bloco 5 — Encerramento</strong>: convite para acessar o AVA, ler o Plano de Disciplina '
    . 'com atenção e participar do Fórum de Apresentação.</p>';

$referencias = seed_ul([
    'ANJOS, Alexandre Martins dos; ANJOS, Rosana Abutakka Vasconcelos dos. Processos de aprendizagem '
        . 'em EaD. Cuiabá: Universidade Federal de Mato Grosso, Secretaria de Tecnologia Educacional, 2018.',
    'DERMEVAL SAVIANI | A Pedagogia Histórico-Crítica. Canal Leituras Brasileiras, YouTube, 2017.',
    'ESCOLA Viva Entrevista Paulo Freire. Canal TV Cultura, YouTube, 2021.',
    'FILATRO, Andrea; PICONEZ, Stela. Contribuições do design instrucional e do Learning Design para '
        . 'a organização do trabalho pedagógico. In: Nuevas Ideas en Informática Educativa, v. 4, '
        . 'p. 81-88, Santiago de Chile, 2008.',
    'FILATRO, Andrea. Design instrucional sob uma perspectiva andragógica. Texto-base, congresso '
        . 'WebCurrículo, 2009.',
    'FIÚZA, Elza. Professor Dermeval Saviani. Wikimedia Commons, 2010. 1 fotografia. CC-BY-4.0.',
    'KENSKI, Vani; SCHULTZ, Janine. Teorias e abordagens pedagógicas (e-book). São Paulo: Senac, 2014.',
    'LIBÂNEO, José Carlos. As teorias pedagógicas modernas revisitadas pelo debate contemporâneo na '
        . 'educação. In: Educação na era do conhecimento em rede e transdisciplinaridade. São Paulo: '
        . 'Alínea, 2005.',
    'PINTO, Joane Vilela; BOSCARIOLI, Clodis. Estilos de aprendizagem na educação a distância: '
        . 'reflexões sobre relações e possibilidades. Revista Humanidades e Inovação, v. 8, n. 54, '
        . 'p. 220-230, 2021.',
    'SANTOS, Mariana Fernandes dos. A construção da autonomia do sujeito aprendiz no contexto da EaD. '
        . 'Revista Associação Brasileira de Educação a Distância, São Paulo, v. 14, p. 21-35, 2015.',
    'SAVIANI, Dermeval. Trabalho e educação: fundamentos ontológicos e históricos. Revista Brasileira '
        . 'de Educação, v. 12, n. 34, jan./abr. 2007.',
    'SAVIANI, Dermeval. Pedagogia histórico-crítica: primeiras aproximações. 11. ed. rev. Campinas: '
        . 'Autores Associados, 2011.',
    'TEORIAS Educacionais Aula 1: Tendências Pedagógicas. Canal IFRO Campus Porto Velho Zona Norte — '
        . 'EaD, YouTube, 2022.',
    'TEORIAS Educacionais Aula 2: O ensino na perspectiva de Paulo Freire. Canal IFRO Campus Porto '
        . 'Velho Zona Norte — EaD, YouTube, 2022.',
    'TESSER, Gelson João. Principais linhas epistemológicas contemporâneas. Educar, Curitiba, 10. '
        . 'ed., mar. 2015 [dez. 1994].',
    'VALLIN, Celso. Educação a Distância e Paulo Freire. Revista Associação Brasileira de Educação a '
        . 'Distância, v. 13, p. 37-56, 2014.',
    'BRASIL. Decreto nº 12.456, de 19 de maio de 2025. Dispõe sobre a oferta de educação a distância '
        . 'por instituições de educação superior. Brasília, DF: Presidência da República, 2025.',
]);

$avaliacaofinaltexto = '<p>A Avaliação Final é destinada aos estudantes que não atingiram Média da '
    . 'Disciplina (MD) igual ou superior a 70 pontos, desde que a MD seja maior ou igual a 40 pontos. '
    . 'Será aplicado um questionário com 04 (quatro) questões dissertativas, uma para cada um dos '
    . 'módulos vistos no curso, cada questão valendo 25 (vinte e cinco) pontos.</p>';

seed_set_customfield('plan_handler', $syllabusid, 'coursedescription', $ementa);
seed_set_customfield('plan_handler', $syllabusid, 'objectives', $objetivos);
seed_set_customfield('plan_handler', $syllabusid, 'contents', $conteudos);
seed_set_customfield('plan_handler', $syllabusid, 'methodology', $metodologia);
seed_set_customfield('plan_handler', $syllabusid, 'presentationscript', $roteirovideo);
seed_set_customfield('plan_handler', $syllabusid, 'generalreferences', $referencias);
seed_set_customfield('plan_handler', $syllabusid, 'finalassessmentinstructions', $avaliacaofinaltexto);
cli_writeln("Campos narrativos do plano preenchidos.");

// 8. Weeks (structural fields).

/**
 * Creates a syllabus week if one with the same title does not already exist.
 *
 * @param int $syllabusid Owning syllabus ID.
 * @param string $title Week title.
 * @param int $duration Duration in hours.
 * @param int $startdate Start date (timestamp).
 * @param int $enddate End date (timestamp).
 * @param int $syncdate Synchronous meeting timestamp, or 0 if there is none this week.
 * @param string $synclink Synchronous meeting access link.
 * @param string $synctopic Synchronous meeting topic.
 * @param int $sortorder Sort order.
 * @return stdClass Week record.
 */
function seed_upsert_week(
    int $syllabusid,
    string $title,
    int $duration,
    int $startdate,
    int $enddate,
    int $syncdate,
    string $synclink,
    string $synctopic,
    int $sortorder
): stdClass {
    global $DB;

    $existing = $DB->get_record('syllabus_weeks', ['syllabusid' => $syllabusid, 'title' => $title]);
    if ($existing) {
        return $existing;
    }

    $now = time();
    $record = (object) [
        'syllabusid'   => $syllabusid,
        'title'        => $title,
        'duration'     => $duration,
        'startdate'    => $startdate,
        'enddate'      => $enddate,
        'syncdate'     => $syncdate,
        'synclink'     => $synclink,
        'synctopic'    => $synctopic,
        'stage'        => 1,
        'sortorder'    => $sortorder,
        'timecreated'  => $now,
        'timemodified' => $now,
    ];
    $record->id = $DB->insert_record('syllabus_weeks', $record);
    return $record;
}

$meetlink = 'https://meet.google.com/zun-iyqg-chj';

$week1 = seed_upsert_week(
    $syllabusid,
    'Capítulo 1 — Teorias Pedagógicas e Estilos de Aprendizagem na EaD',
    8,
    mktime(0, 0, 0, 5, 7, 2026),
    mktime(23, 59, 59, 5, 14, 2026),
    mktime(19, 30, 0, 5, 14, 2026),
    $meetlink,
    'Apresentação da disciplina. Apresentação do professor(a). Introdução às Teorias, '
        . 'Metodologias e Planejamento Pedagógico na EaD.',
    1
);

$week2 = seed_upsert_week(
    $syllabusid,
    'Capítulo 2 — Abordagens da Psicologia da Aprendizagem e da Educação na EaD e EPT',
    7,
    mktime(0, 0, 0, 5, 15, 2026),
    mktime(23, 59, 59, 5, 21, 2026),
    0,
    '',
    '',
    2
);

$week3 = seed_upsert_week(
    $syllabusid,
    'Capítulo 3 — Planejamento Pedagógico na EaD para a EPT: Design Instrucional',
    8,
    mktime(0, 0, 0, 5, 22, 2026),
    mktime(23, 59, 59, 5, 28, 2026),
    mktime(20, 0, 0, 5, 27, 2026),
    $meetlink,
    'Planejamento para EaD com Inteligência Artificial.',
    3
);

$week4 = seed_upsert_week(
    $syllabusid,
    'Capítulo 4 — Metodologias de Ensino-Aprendizagem na EaD',
    7,
    mktime(0, 0, 0, 5, 29, 2026),
    mktime(23, 59, 59, 6, 4, 2026),
    0,
    '',
    '',
    4
);
cli_writeln("Semanas criadas: 4 (2 com encontro síncrono).");

// 9. Week-level narrative Custom Fields.

$interacaopadrao = '<p>Fórum no Moodle; mensagem privada para feedback individual quando necessário.</p>';
$duvidaspadrao = '<p>Ficou com dúvidas? Utilize o Fórum Geral da disciplina!</p>';

$w1detalhes = '<p>Bem-vindo(a) à Semana 1! Esta semana abrimos nossa jornada explorando as teorias '
    . 'pedagógicas que fundamentam a Educação a Distância. Questão norteadora: por que conhecer as '
    . 'teorias pedagógicas é essencial para quem planeja e pratica a EaD na EPT?</p>'
    . '<p>Partiremos de um panorama amplo sobre o campo epistemológico das teorias pedagógicas — '
    . 'abordagens críticas e não críticas — e avançaremos para as contribuições de Paulo Freire '
    . '(educação libertadora e dialogicidade) e de Dermeval Saviani (Pedagogia Histórico-Crítica). Ao '
    . 'final, estudaremos os estilos de aprendizagem e sua relação com o design pedagógico em ambientes '
    . 'virtuais.</p>'
    . '<p>Ao final desta semana, o(a) estudante será capaz de:</p>'
    . seed_ul([
        'Identificar as principais correntes e abordagens das teorias pedagógicas e sua relevância '
            . 'para a EaD e a EPT;',
        'Relacionar os fundamentos freireanos (dialogicidade, educação libertadora, autonomia) com a '
            . 'prática docente em ambientes virtuais;',
        'Reconhecer os pressupostos da Pedagogia Histórico-Crítica de Saviani e suas implicações para '
            . 'o ensino na EPT;',
        'Distinguir diferentes estilos de aprendizagem e suas implicações para o planejamento de '
            . 'materiais e atividades na EaD.',
    ]);

$w1apoio = seed_ul([
    'Capítulo 1 do material digital UFSC/SETEC-MEC (páginas 2 a 6)',
    'Videoaula: Teorias Educacionais Aula 1 — Tendências Pedagógicas (YouTube/IFRO)',
    'Videoaula: Teorias Educacionais Aula 2 — O ensino na perspectiva de Paulo Freire (YouTube/IFRO)',
]);
$w1complementar = seed_ul([
    'VALLIN, C. Educação a Distância e Paulo Freire. RBAAD, v. 13, 2014.',
    'SAVIANI, D. Pedagogia histórico-crítica: primeiras aproximações. Autores Associados, 2011.',
]);
$w1notas = '<p>Roteiro de estudos:</p>'
    . seed_ol([
        'Atividades assíncronas: ler o Capítulo 1 e assistir às videoaulas indicadas;',
        'Encontro síncrono: participar no dia 14/05/2026;',
        'Atividade da semana: participar do Fórum Avaliativo 1, atento(a) ao prazo de postagem.',
    ])
    . $duvidaspadrao;

$w2detalhes = '<p>Bem-vindo(a) à Semana 2! Nesta etapa, aprofundamos o olhar sobre como a Psicologia '
    . 'da Aprendizagem fundamenta os processos educativos na EaD e na EPT. Questão norteadora: como as '
    . 'diferentes abordagens psicológicas explicam o ato de aprender e o que isso significa para o '
    . 'design de cursos a distância destinados a jovens e adultos trabalhadores?</p>'
    . '<p>Estudaremos o Behaviorismo, o Construtivismo (Piaget) e o Socioconstrutivismo (Vygotsky), e '
    . 'refletiremos sobre a Andragogia — campo que trata especificamente da aprendizagem de adultos.</p>'
    . '<p>Ao final desta semana, o(a) estudante será capaz de:</p>'
    . seed_ul([
        'Diferenciar as abordagens behaviorista, construtivista e socioconstrutivista e identificar '
            . 'suas influências no design de cursos EaD;',
        'Reconhecer as contribuições de Piaget e Vygotsky para a compreensão do desenvolvimento e da '
            . 'aprendizagem;',
        'Compreender os princípios da Andragogia e sua aplicabilidade em cursos EaD voltados para '
            . 'jovens e adultos na EPT;',
        'Relacionar as abordagens estudadas com estratégias pedagógicas para ambientes virtuais de '
            . 'aprendizagem.',
    ]);

$w2apoio = seed_ul([
    'Capítulo 2 do material digital (páginas 7 a 12)',
    'Videoaula: Teorias Educacionais Aula 3 — A Teoria Histórico-Cultural (YouTube/IFRO)',
    'Videoaula: Teorias Educacionais Aula 4 — Construtivismo e Aprendizagem Significativa (YouTube/IFRO)',
    'Vídeo: Aprendizagem crítica e criativa na cultura digital — Prof. Daniel Mill',
]);
$w2complementar = seed_ul([
    'ANJOS, A.; ANJOS, R. Processos de aprendizagem em EaD. UFMT, 2018.',
    'ANDERSON, T.; DRON, J. Três gerações de pedagogia de EaD. 2012.',
]);
$w2notas = '<p>Não há encontro síncrono nesta semana. Participar do Fórum Avaliativo 2, trazendo '
    . 'reflexões sobre como as abordagens psicológicas estudadas podem orientar práticas pedagógicas '
    . 'na EaD-EPT.</p>' . $duvidaspadrao;

$w3detalhes = '<p>Bem-vindo(a) à Semana 3! Chegamos ao coração do planejamento: nesta semana '
    . 'estudamos o design instrucional contextualizado, forma de organizar e estruturar '
    . 'intencionalmente todo o processo de ensino-aprendizagem na EaD. Questão norteadora: como '
    . 'planejar, de forma articulada e crítica, um percurso formativo para a EaD na EPT?</p>'
    . '<p>Estudaremos os fundamentos e etapas do design instrucional, sua relação com as teorias '
    . 'pedagógicas dos capítulos anteriores, e como o planejamento pedagógico se concretiza no AVA. '
    . 'Teremos também o 2.º encontro síncrono nesta semana!</p>'
    . '<p>Ao final desta semana, o(a) estudante será capaz de:</p>'
    . seed_ul([
        'Compreender os fundamentos do design instrucional e sua importância para a EaD na EPT;',
        'Articular as teorias pedagógicas e psicológicas ao processo de design instrucional;',
        'Identificar os elementos essenciais do planejamento pedagógico para cursos a distância;',
        'Reconhecer como o AVA se constitui como espaço pedagógico planejado e intencional.',
    ]);

$w3apoio = seed_ul([
    'Capítulo 3 do material digital UFSC/SETEC-MEC',
    'FILATRO, A.; PICONEZ, S. Contribuições do design instrucional e do Learning Design. In: '
        . 'WebCurrículo, 2008.',
]);
$w3complementar = seed_ul([
    'FILATRO, A. Design instrucional sob uma perspectiva andragógica. 2009.',
]);
$w3notas = '<p>Roteiro de estudos:</p>'
    . seed_ol([
        'Atividades assíncronas: ler o Capítulo 3 e explorar os materiais complementares do AVA;',
        'Encontro síncrono: participar no dia 27/05/2026;',
        'Atividade da semana: participar do Fórum Avaliativo 3, atento(a) ao prazo de postagem.',
    ])
    . $duvidaspadrao;

$w4detalhes = '<p>Bem-vindo(a) à última semana! Chegamos ao capítulo mais prático desta jornada: as '
    . 'metodologias de ensino-aprendizagem na EaD, com foco nas Metodologias Ativas. Questão '
    . 'norteadora: como tornar a aprendizagem mais ativa, participativa e significativa em ambientes '
    . 'virtuais de aprendizagem na EPT?</p>'
    . '<p>Estudaremos as principais metodologias ativas — Sala de Aula Invertida, Aprendizagem '
    . 'Baseada em Problemas (ABP), Gamificação e outras — e refletiremos sobre suas possibilidades e '
    . 'limites para a prática pedagógica na EPT. Também realizaremos o Questionário Final, que '
    . 'abrangerá todos os conteúdos das quatro semanas.</p>'
    . '<p>Ao final desta semana, o(a) estudante será capaz de:</p>'
    . seed_ul([
        'Identificar as principais metodologias ativas e suas características centrais;',
        'Compreender como as metodologias ativas se articulam com as teorias pedagógicas e '
            . 'psicológicas estudadas;',
        'Reconhecer as possibilidades e os desafios de implementação das metodologias ativas em '
            . 'cursos EaD na EPT;',
        'Propor estratégias pedagógicas ativas adequadas a contextos específicos da EPT em '
            . 'modalidade a distância.',
    ]);

$w4apoio = seed_ul([
    'Capítulo 4 do material digital UFSC/SETEC-MEC',
]);
$w4complementar = seed_ul([
    'BRASIL. Decreto nº 12.456, de 19 de maio de 2025. Dispõe sobre a oferta de educação a '
        . 'distância por instituições de educação superior. Brasília, DF: Presidência da República, 2025.',
]);
$w4notas = '<p>Não há encontro síncrono nesta semana. Realizar o Fórum Avaliativo 4 e, em seguida, o '
    . 'Questionário Final disponíveis na seção Atividades do AVA.</p>' . $duvidaspadrao;

$weekfields = [
    $week1->id => [$w1detalhes, $w1apoio, $w1complementar, $interacaopadrao, $w1notas],
    $week2->id => [$w2detalhes, $w2apoio, $w2complementar, $interacaopadrao, $w2notas],
    $week3->id => [$w3detalhes, $w3apoio, $w3complementar, $interacaopadrao, $w3notas],
    $week4->id => [$w4detalhes, $w4apoio, $w4complementar, $interacaopadrao, $w4notas],
];
$weekshortnames = ['details', 'supportmaterial', 'supplementarymaterial', 'interactiontools', 'notes'];
foreach ($weekfields as $weekid => $values) {
    foreach ($weekshortnames as $index => $shortname) {
        seed_set_customfield('week_handler', $weekid, $shortname, $values[$index]);
    }
}
cli_writeln("Campos narrativos das semanas preenchidos.");

// 10. Activities (structural fields).

/**
 * Creates a syllabus activity if one with the same title does not already exist in the week.
 *
 * @param int $weekid Owning week ID.
 * @param string $title Activity title.
 * @param string $type Activity type, e.g. Fórum, Questionário.
 * @param string $category Activity category, e.g. Online.
 * @param int $startdate Start date (timestamp).
 * @param int $enddate End date (timestamp).
 * @param float $points Points.
 * @param int $sortorder Sort order.
 * @return stdClass Activity record.
 */
function seed_upsert_activity(
    int $weekid,
    string $title,
    string $type,
    string $category,
    int $startdate,
    int $enddate,
    float $points,
    int $sortorder
): stdClass {
    global $DB;

    $existing = $DB->get_record('syllabus_activities', ['weekid' => $weekid, 'title' => $title]);
    if ($existing) {
        return $existing;
    }

    $now = time();
    $record = (object) [
        'weekid'       => $weekid,
        'title'        => $title,
        'type'         => $type,
        'category'     => $category,
        'startdate'    => $startdate,
        'enddate'      => $enddate,
        'points'       => $points,
        'sortorder'    => $sortorder,
        'timecreated'  => $now,
        'timemodified' => $now,
    ];
    $record->id = $DB->insert_record('syllabus_activities', $record);
    return $record;
}

$activity1 = seed_upsert_activity(
    $week1->id,
    'Fórum Avaliativo 1 — Teorias Pedagógicas e a Prática na EaD',
    'Fórum',
    'Online',
    mktime(0, 0, 0, 5, 7, 2026),
    mktime(23, 59, 59, 5, 14, 2026),
    15.0,
    1
);
$activity2 = seed_upsert_activity(
    $week2->id,
    'Fórum Avaliativo 2 — Psicologia da Aprendizagem e a EaD na EPT',
    'Fórum',
    'Online',
    mktime(0, 0, 0, 5, 15, 2026),
    mktime(23, 59, 59, 5, 21, 2026),
    15.0,
    1
);
$activity3 = seed_upsert_activity(
    $week3->id,
    'Fórum Avaliativo 3 — Planejamento Pedagógico na EaD para a EPT: Design Instrucional',
    'Fórum',
    'Online',
    mktime(0, 0, 0, 5, 22, 2026),
    mktime(23, 59, 59, 5, 28, 2026),
    15.0,
    1
);
$activity4 = seed_upsert_activity(
    $week4->id,
    'Fórum Avaliativo 4 — Metodologias Ativas na EaD-EPT',
    'Fórum',
    'Online',
    mktime(0, 0, 0, 5, 29, 2026),
    mktime(23, 59, 59, 6, 4, 2026),
    15.0,
    1
);
$activity5 = seed_upsert_activity(
    $week4->id,
    'Questionário Avaliativo — Todos os Capítulos',
    'Questionário',
    'Online',
    mktime(0, 0, 0, 5, 29, 2026),
    mktime(23, 59, 59, 6, 4, 2026),
    40.0,
    2
);
cli_writeln("Atividades criadas: 4 fóruns avaliativos + 1 questionário.");

// 11. Activity-level narrative Custom Fields.

$criteriosforum = seed_ul([
    'Clareza e organização das ideias — 4 pontos',
    'Argumentação consistente — 4 pontos',
    'Relação com a leitura — 3 pontos',
    'Criatividade e originalidade — 2 pontos',
    'Correção gramatical — 2 pontos',
]);
$tutoriaforumpadrao = '<p>Acessar o fórum pelo menos uma vez ao dia. Realizar intervenções que achar '
    . 'necessárias. Realizar correção das postagens conforme critérios de avaliação.</p>';

$a1instrucoes = '<p>Olá, estudante! Chegou a hora de colocarmos em prática o que aprendemos nesta '
    . 'semana. O objetivo desta atividade é que você articule as teorias pedagógicas estudadas — em '
    . 'especial as abordagens freireana e histórico-crítica — com a realidade da EaD na EPT.</p>'
    . '<p>O que você precisa fazer?</p>'
    . seed_ol([
        'Leia o Capítulo 1 do material digital e assista às videoaulas indicadas.',
        'No fórum, responda à questão de debate.',
    ]);

$a2instrucoes = '<p>Olá, estudante! Chegou a hora de colocarmos em prática o que aprendemos nesta '
    . 'semana. O objetivo desta atividade é que você reflita sobre como se dá o aprendizado de '
    . 'jovens e adultos na EPT e EaD.</p>'
    . '<p>O que você precisa fazer?</p>'
    . seed_ol([
        'Leia o Capítulo 2 do material digital e assista às videoaulas indicadas.',
        'No fórum, responda à questão de debate.',
    ]);

$a3instrucoes = '<p>Olá, estudante! Chegou a hora de colocarmos em prática o que aprendemos nesta '
    . 'semana. O objetivo desta atividade é que você reflita sobre planejamento em EaD, a partir de '
    . 'eventuais experiências.</p>'
    . '<p>O que você precisa fazer?</p>'
    . seed_ol([
        'Leia o Capítulo 3 do material digital e assista às videoaulas indicadas.',
        'No fórum, responda à questão de debate.',
    ]);

$a4instrucoes = '<p>Olá, estudante! Chegou a hora de colocarmos em prática o que aprendemos nesta '
    . 'semana. O objetivo desta atividade é refletir sobre o uso de Metodologias Ativas na EaD em '
    . 'EPT.</p>'
    . '<p>O que você precisa fazer?</p>'
    . seed_ol([
        'Leia o Capítulo 4 do material digital e assista às videoaulas indicadas.',
        'No fórum, responda à questão de debate.',
    ]);

$a5instrucoes = '<p>Chegamos ao momento de consolidar toda a aprendizagem construída ao longo das '
    . 'quatro semanas. O questionário final abrange os conteúdos dos quatro capítulos do material de '
    . 'base.</p>'
    . '<p>Orientações importantes:</p>'
    . seed_ul([
        'O questionário possui 10 (dez) questões objetivas (múltipla escolha);',
        'Revise seus materiais antes de iniciar. Você terá 3 tentativas e tempo limite de 1h;',
        'Após o prazo, o questionário será encerrado automaticamente.',
    ]);
$a5criterios = '<p>Correção automática pelo AVA.</p>';
$a5tutoria = '<p>Verificar a realização por parte dos estudantes. A atividade tem correção '
    . 'automática, não sendo necessário realizar avaliação manual.</p>';

$activityfields = [
    $activity1->id => [$a1instrucoes, $criteriosforum, $tutoriaforumpadrao],
    $activity2->id => [$a2instrucoes, $criteriosforum, $tutoriaforumpadrao],
    $activity3->id => [$a3instrucoes, $criteriosforum, $tutoriaforumpadrao],
    $activity4->id => [$a4instrucoes, $criteriosforum, $tutoriaforumpadrao],
    $activity5->id => [$a5instrucoes, $a5criterios, $a5tutoria],
];
$activityshortnames = ['studentinstructions', 'gradingcriteria', 'tutorguidance'];
foreach ($activityfields as $activityid => $values) {
    foreach ($activityshortnames as $index => $shortname) {
        seed_set_customfield('activity_handler', $activityid, $shortname, $values[$index]);
    }
}
cli_writeln("Campos narrativos das atividades preenchidos.");

// 12. Left in 'draft', on purpose — submit()/approve()/request_changes() are exercised
// manually by whoever is testing, as teacher/coordinator, instead of the seed already
// landing pre-approved.

// 13. Summary.

$wwwroot = $CFG->wwwroot;
$viewurl = "{$wwwroot}/mod/syllabus/view.php?id={$cm->id}";

cli_writeln("\n" . str_repeat('=', 60));
cli_writeln("SEED CONCLUÍDO");
cli_writeln(str_repeat('=', 60));
cli_writeln("Plano:    {$viewurl}");
cli_writeln("Syllabus ID: {$syllabusid}");
cli_writeln("");
cli_writeln("USUÁRIOS (senha: " . SEED_PASSWORD . ")");
cli_writeln(str_pad("Username", 28) . str_pad("Nome", 22) . "Papel");
cli_writeln(str_repeat('-', 65));
cli_writeln(
    str_pad($teacher->username, 28)
    . str_pad($teacher->firstname . ' ' . $teacher->lastname, 22)
    . 'Professor(a) autor(a) do plano'
);
cli_writeln(
    str_pad($coordinator->username, 28)
    . str_pad($coordinator->firstname . ' ' . $coordinator->lastname, 22)
    . 'Coordenador(a) (revisor)'
);
cli_writeln(
    str_pad($tutor->username, 28)
    . str_pad($tutor->firstname . ' ' . $tutor->lastname, 22)
    . 'Tutor(a) (visão de tutoria)'
);
foreach ($students as $student) {
    cli_writeln(
        str_pad($student->username, 28)
        . str_pad($student->firstname . ' ' . $student->lastname, 22)
        . 'Estudante'
    );
}
cli_writeln(str_repeat('=', 60));
cli_writeln("Para recriar tudo do zero: php mod/syllabus/cli/seed_pt_br.php --reset");
