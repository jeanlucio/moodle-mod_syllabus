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
 * Post-installation seed for mod_syllabus's default Custom Fields template.
 *
 * @package mod_syllabus
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seeds the three Custom Fields areas (plano, aula, atividade) with the narrative fields
 * of the original three plan documents, so a fresh install already looks like today's
 * template. The institution can later add, remove or relabel fields via
 * Site administration > Plugins > Activity modules > Syllabus, without a plugin release.
 *
 * @return void
 */
function xmldb_syllabus_install(): void {
    $areas = [
        'plano_handler' => [
            'category' => 'Conteúdo do Plano',
            'fields' => [
                'ementa' => 'Ementa da Disciplina',
                'objetivos' => 'Objetivos (competências mínimas e habilidades pretendidas)',
                'conteudos' => 'Conteúdos',
                'metodologia' => 'Metodologia',
            ],
        ],
        'aula_handler' => [
            'category' => 'Conteúdo da Aula',
            'fields' => [
                'detalhamento' => 'Detalhamento da aula',
                'bibliografiaapoio' => 'Bibliografia — Material de apoio',
                'bibliografiacomplementar' => 'Bibliografia — Material complementar',
                'ferramentasinteracao' => 'Ferramentas de Interação',
                'observacoes' => 'Observações/Providências/Encaminhamentos',
            ],
        ],
        'atividade_handler' => [
            'category' => 'Avaliação e Acompanhamento',
            'fields' => [
                'orientacoesestudantes' => 'Orientações aos estudantes',
                'criteriosavaliacao' => 'Critérios de correção/avaliação',
                'acompanhamentotutoria' => 'Acompanhamento de Tutoria',
            ],
        ],
    ];

    foreach ($areas as $handlerclass => $definition) {
        $classname = "mod_syllabus\\customfield\\{$handlerclass}";
        $handler = $classname::create();
        $categoryid = $handler->create_category($definition['category']);
        $category = core_customfield\category_controller::create($categoryid);

        foreach ($definition['fields'] as $shortname => $name) {
            $record = (object) [
                'shortname' => $shortname,
                'name' => $name,
                'type' => 'textarea',
                'categoryid' => $categoryid,
                'configdata' => [],
            ];
            $field = core_customfield\field_controller::create(0, $record, $category);
            core_customfield\api::save_field_configuration($field, $record);
        }
    }
}
