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
 * Portuguese (Brazil) language strings for mod_syllabus.
 *
 * @package    mod_syllabus
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['academicperiod'] = 'Ano/semestre';
$string['actionnotallowed'] = 'Ação não permitida';
$string['activities'] = 'Atividades';
$string['activitycategory'] = 'Categoria';
$string['activityenddate'] = 'Data de término';
$string['activityname'] = 'Nome do plano';
$string['activitypoints'] = 'Pontuação';
$string['activitystartdate'] = 'Data de início';
$string['activitytitle'] = 'Título da atividade';
$string['activitytype'] = 'Tipo';
$string['activitytypecategory'] = 'Tipo e categoria da atividade';
$string['activitytypehelp'] = 'Escolha o recurso do AVA usado e se a atividade é síncrona, assíncrona ou online.';
$string['activitytypehelpfull'] = 'Tipo: indique o recurso do AVA usado nesta atividade (Fórum, Questionário, Tarefa, Jogo, etc.) ou marque como Presencial, quando for o caso. Categoria: Síncrona para atividades realizadas ao vivo; Assíncrona para as que o estudante realiza no próprio tempo; Online como categoria geral para o que acontece no AVA sem horário marcado. Lembre-se da Instrução Normativa DEaD 10/2021: é exigida no mínimo 1 atividade assíncrona a cada 10h de carga horária da disciplina.';
$string['addactivity'] = 'Adicionar atividade';
$string['addweek'] = 'Adicionar aula';
$string['allchangessaved'] = 'Todas as alterações salvas';
$string['cannotapproveown'] = 'Você não pode aprovar nem solicitar ajustes num plano que você mesmo submeteu.';
$string['cannotsubmitstatus'] = 'Este plano não pode ser submetido a partir do status atual.';
$string['categoryactivity'] = 'Avaliação e Acompanhamento';
$string['categoryasynchronous'] = 'Assíncrona';
$string['categoryhelp'] = 'Orientações do modelo';
$string['categoryonline'] = 'Online';
$string['categoryplan'] = 'Conteúdo do Plano';
$string['categorysynchronous'] = 'Síncrona';
$string['categoryweek'] = 'Conteúdo da Aula';
$string['changesrequestedreason'] = 'Motivo do ajuste solicitado';
$string['characterisation'] = 'Caracterização';
$string['characterisationhelp'] = 'Curso, Nome da disciplina e Professor(a) são preenchidos automaticamente; preencha Ano/semestre, Período da disciplina e Carga horária Total — o Vídeo de apresentação é opcional.';
$string['characterisationhelpfull'] = 'O modelo pede Curso, Nome da disciplina e Professor(a) — esses três já são preenchidos automaticamente a partir do Moodle (categoria do curso, disciplina e quem submeteu o plano) e não aparecem aqui como campo. Preencha apenas: Ano/semestre (ex.: 2026.1); Período da disciplina, com a data de início e fim conforme o calendário do curso para o semestre; Carga horária Total, de acordo com o PPC da disciplina; e, opcionalmente, o Vídeo de apresentação — um vídeo de até 5 minutos em que você se apresenta e apresenta a disciplina, cobrindo 5 blocos: (1) abertura e boas-vindas, (2) objetivos e relevância da disciplina, (3) metodologia, como os momentos síncronos e assíncronos se articulam, (4) acompanhamento e avaliação, e (5) encerramento com um convite à participação; o roteiro completo (a transcrição do vídeo) deve ir no campo Apresentação do Professor e da Disciplina, logo abaixo.';
$string['collapseall'] = 'Recolher tudo';
$string['confirmdeleteactivity'] = 'Excluir esta atividade? Esta ação não pode ser desfeita.';
$string['confirmdeleteweek'] = 'Excluir esta aula e todas as suas atividades? Esta ação não pode ser desfeita.';
$string['confirmresetfielddescription'] = 'Restaurar a descrição deste campo para o texto padrão do plugin? Qualquer customização já feita será perdida.';
$string['confirmunpublish'] = 'Voltar este plano para rascunho e ocultá-lo de novo? Você pode reenviá-lo quando terminar.';
$string['contents'] = 'Conteúdos';
$string['contentshelp'] = 'Descreva os tópicos abordados, na ordem em que serão ensinados.';
$string['contentshelpfull'] = 'Detalhe os conteúdos a serem trabalhados ao longo da disciplina, de acordo com a ementa e os objetivos definidos acima — normalmente organizados na mesma ordem em que aparecerão nas aulas/semanas.';
$string['coursedescription'] = 'Ementa da Disciplina';
$string['coursedescriptionhelp'] = 'Resuma o que a disciplina aborda e sua importância na formação do estudante.';
$string['coursedescriptionhelpfull'] = 'Escreva uma descrição panorâmica dos conteúdos trabalhados na disciplina. Consulte a ementa oficial disponível no Projeto Pedagógico do Curso (PPC) — você não precisa se restringir a ela, mas o que está lá precisa necessariamente estar contemplado aqui.';
$string['courseenddate'] = 'Fim do período';
$string['courseperiod'] = 'Período da disciplina';
$string['coursestartdate'] = 'Início do período';
$string['deadline'] = 'Prazo';
$string['defaultactivitytitle'] = 'Atividade {$a}';
$string['defaultweektitle'] = 'Aula {$a}';
$string['deleteactivity'] = 'Excluir atividade';
$string['deleteweek'] = 'Excluir aula';
$string['details'] = 'Detalhamento da aula';
$string['detailshelp'] = 'Descreva o que acontece na aula desta semana.';
$string['detailshelpfull'] = 'Escreva um texto simples e dialógico, em primeira pessoa, conversando diretamente com o estudante — dê boas-vindas à semana, apresente o tema central (uma pergunta norteadora ajuda a dar propósito) e faça uma ponte com a aula anterior. Inclua os objetivos específicos desta aula/semana (\'Ao final desta aula, você será capaz de...\', com verbos de ação) e um roteiro de estudos em passos: primeiro os materiais assíncronos a explorar, depois o encontro síncrono (se houver), e por fim a atividade avaliativa da semana — sem repetir aqui o comando completo da atividade, que já tem seu próprio campo.';
$string['discipline'] = 'Disciplina';
$string['eventplanapproved'] = 'Plano aprovado';
$string['eventplanchangesrequested'] = 'Ajustes solicitados no plano';
$string['eventplansubmitted'] = 'Plano submetido';
$string['expandall'] = 'Expandir tudo';
$string['fielddescriptionreset'] = 'Descrição do campo restaurada para o texto padrão.';
$string['finalassessment'] = 'Avaliação Final';
$string['finalassessmentenddate'] = 'Data de término';
$string['finalassessmenthelp'] = 'Planeje a Avaliação Final da disciplina, uma atividade de recuperação à parte da(s) etapa(s) normal(is).';
$string['finalassessmenthelpfull'] = 'Preencha este bloco para a Avaliação Final da disciplina — ela é destinada aos estudantes que não atingiram média da disciplina (MD) igual ou superior a 70, desde que tenham obtido MD maior ou igual a 40, e deve abranger os conteúdos ministrados em todo o componente curricular. Ela tem seu próprio total de 100 pontos, à parte da(s) etapa(s) normal(is): nunca entra na soma de 100 pontos de uma etapa, nem no método de cálculo escolhido para as etapas normais.';
$string['finalassessmentinstructionshelp'] = 'Explique ao estudante em que consiste a Avaliação Final e como realizá-la.';
$string['finalassessmentinstructionshelpfull'] = 'Escreva um enunciado detalhado, claro e objetivo, conversando diretamente com o estudante, da mesma forma que faria nas orientações de uma atividade comum: o objetivo da avaliação, o que fazer, e em quais conteúdos da disciplina ela se baseia.';
$string['finalassessmentpoints'] = 'Pontos';
$string['finalassessmentstartdate'] = 'Data de início';
$string['finalassessmenttitle'] = 'Título';
$string['finalassessmenttype'] = 'Tipo';
$string['generalreferences'] = 'Referências';
$string['generalreferenceshelp'] = 'Liste as referências bibliográficas obrigatórias e complementares da disciplina.';
$string['generalreferenceshelpfull'] = 'Liste a bibliografia básica e complementar utilizada na disciplina como um todo, de acordo com as normas ABNT — atente-se para as referências já previstas no PPC do curso.';
$string['grademethod'] = 'Método de cálculo';
$string['grademethodaverage'] = 'Média das notas por etapa';
$string['grademethodhelp'] = 'Como as notas das etapas devem ser combinadas num resultado final — apenas informativo, exibido nas visões de leitura; este plano nunca calcula uma nota real.';
$string['grademethodsum'] = 'Somatório dos pontos por etapa';
$string['gradingcriteria'] = 'Critérios de correção/avaliação';
$string['gradingcriteriahelp'] = 'Descreva como esta atividade será corrigida, incluindo o gabarito, se houver.';
$string['gradingcriteriahelpfull'] = 'Descreva os critérios de correção e, se houver gabarito, informe-o aqui. Para atividades objetivas (questionários), nunca indique apenas o número da questão e a letra da alternativa — o Moodle pode embaralhar tanto as questões quanto as alternativas entre os estudantes, então transcreva o texto completo da alternativa correta, seguido de uma breve justificativa. Para atividades subjetivas, detalhe os critérios em tópicos, com a pontuação de cada um (ex.: qualidade argumentativa — 5 pontos; uso da norma padrão — 5 pontos; uso adequado de referências — 5 pontos).';
$string['interactiontools'] = 'Ferramentas de Interação';
$string['interactiontoolshelp'] = 'Liste as ferramentas usadas para interagir com os estudantes nesta semana (fórum, chat, encontro etc.).';
$string['interactiontoolshelpfull'] = 'Detalhe como as ferramentas de interação (fórum, café virtual, chat, e-mail etc.) serão usadas nesta aula — por exemplo, um fórum no café virtual para diagnóstico do grupo, um chat de apresentação, ou o e-mail como canal principal de dúvidas, informando o prazo de retorno esperado.';
$string['invalidarea'] = 'Área de campo personalizado inválida.';
$string['invalidfield'] = 'Campo personalizado inválido.';
$string['managefieldsactivity'] = 'Gerenciar campos de atividade';
$string['managefieldshelp'] = 'Gerenciar campos de orientação do modelo';
$string['managefieldsplan'] = 'Gerenciar campos do plano';
$string['managefieldsweek'] = 'Gerenciar campos de aula';
$string['messagebodyapproved'] = 'Seu plano de disciplina "{$a}" foi aprovado e já está visível para estudantes e tutores.';
$string['messagebodychangesrequested'] = 'A coordenação solicitou ajustes no seu plano de disciplina "{$a->name}": {$a->reason}';
$string['messagebodysubmitted'] = 'O plano de disciplina "{$a}" foi submetido e está aguardando sua revisão.';
$string['messageprovider:plan_approved'] = 'Plano aprovado';
$string['messageprovider:plan_changes_requested'] = 'Ajustes solicitados no plano';
$string['messageprovider:plan_submitted'] = 'Plano submetido para revisão';
$string['messagesubjectapproved'] = 'Plano de disciplina aprovado: {$a}';
$string['messagesubjectchangesrequested'] = 'Ajustes solicitados no seu plano de disciplina: {$a}';
$string['messagesubjectsubmitted'] = 'Plano de disciplina submetido para revisão: {$a}';
$string['methodology'] = 'Metodologia';
$string['methodologyhelp'] = 'Descreva como as aulas acontecerão: exposições, atividades, ferramentas, ritmo.';
$string['methodologyhelpfull'] = 'Descreva como as aulas acontecerão e quais estratégias pedagógicas serão usadas — sempre conectando cada estratégia e atividade aos objetivos de aprendizagem definidos acima: se um objetivo pede que o estudante aplique um conhecimento, que atividade proporciona essa aplicação? Organize o texto em torno de três eixos: os Encontros Síncronos (o espaço para aprofundar temas complexos, debater e construir conhecimento coletivamente), as Atividades Assíncronas (videoaulas, textos-base, podcasts e outros materiais que o estudante explora no próprio tempo, servindo de base para os encontros síncronos) e as Atividades Avaliativas (de caráter processual e contínuo, pensadas não para medir memorização, mas para que o estudante aplique, analise e sintetize o que aprendeu, sempre alinhadas aos objetivos da unidade correspondente). Escreva em primeira pessoa, dirigindo-se diretamente ao estudante.';
$string['modulename'] = 'Plano de Disciplina';
$string['modulename_help'] = 'A atividade Plano de Disciplina permite que o professor preencha um único plano de curso estruturado, submeta para aprovação da coordenação e, uma vez aprovado, publique automaticamente visões específicas por papel para tutores e estudantes.';
$string['modulenameplural'] = 'Planos de Disciplina';
$string['nosyllabusincourse'] = 'Ainda não há nenhum plano de disciplina neste curso.';
$string['notawaitingreview'] = 'Este plano não está aguardando revisão no momento.';
$string['notes'] = 'Observações/Providências/Encaminhamentos';
$string['noteshelp'] = 'Registre observações, providências pendentes ou encaminhamentos para a equipe de tutoria.';
$string['noteshelpfull'] = 'Registre aqui qualquer observação, recurso ou providência que o tutor precise organizar previamente para esta aula/atividade — se não houver nada a registrar, o campo pode ficar em branco.';
$string['noweeksyet'] = 'Nenhuma aula foi adicionada ainda.';
$string['objectives'] = 'Objetivos';
$string['objectiveshelp'] = 'Liste o que o estudante deve ser capaz de fazer ao final da disciplina.';
$string['objectiveshelpfull'] = 'Estabeleça os objetivos de aprendizagem a serem atingidos — o que se espera que o estudante aprenda durante e ao final da disciplina. Podem ser divididos em objetivo geral e objetivos específicos, sempre focados no desenvolvimento do estudante, não no conteúdo em si. Comece cada objetivo com um verbo no infinitivo, por exemplo: Compreender a realidade em que se assenta o sistema educacional brasileiro; Aplicar conhecimentos de informática na educação; Refletir sobre o uso das tecnologias nos processos educativos; Conhecer o ambiente virtual de aprendizagem Moodle.';
$string['onlyapprovedcanunpublish'] = 'Somente um plano aprovado pode ser despublicado.';
$string['plannotavailable'] = 'Este plano ainda não está disponível.';
$string['pluginadministration'] = 'Administração do Plano de Disciplina';
$string['pluginname'] = 'Plano de Disciplina';
$string['presentationscript'] = 'Apresentação do Professor e da Disciplina';
$string['presentationscripthelp'] = 'Apresente-se e apresente a disciplina ao estudante, como uma mensagem de boas-vindas.';
$string['presentationscripthelpfull'] = 'Escreva aqui a transcrição completa (o roteiro) do vídeo de apresentação mencionado na Caracterização, com até 5 minutos, dividido em 5 blocos: (1) Abertura e boas-vindas, um ambiente acolhedor, com uma breve apresentação sua; (2) O coração da disciplina, o objetivo geral e a relevância da disciplina, e as principais habilidades que o estudante desenvolverá; (3) Nossa jornada de aprendizagem, a metodologia, explicando como os momentos síncronos e assíncronos se articulam, com um exemplo concreto de um tema da disciplina; (4) Acompanhamento e avaliação, como o progresso do estudante será avaliado, com ênfase no caráter formativo, não apenas em notas; (5) Encerramento e próximos passos, uma mensagem motivadora e um convite claro para a primeira ação do estudante no AVA (ex.: participar do Fórum de Apresentação).';
$string['presentationvideo'] = 'Vídeo de apresentação';
$string['privacy:metadata:syllabus'] = 'Registra quem submeteu, revisou e despublicou cada plano de disciplina.';
$string['privacy:metadata:syllabus:reviewedby'] = 'O ID do usuário que revisou o plano pela última vez.';
$string['privacy:metadata:syllabus:submittedby'] = 'O ID do usuário que submeteu o plano pela última vez.';
$string['privacy:metadata:syllabus:timereviewed'] = 'A data em que o plano foi revisado pela última vez.';
$string['privacy:metadata:syllabus:timesubmitted'] = 'A data em que o plano foi submetido pela última vez.';
$string['privacy:metadata:syllabus:timeunpublished'] = 'A data em que o plano foi despublicado pela última vez.';
$string['privacy:metadata:syllabus:unpublishedby'] = 'O ID do usuário que despublicou o plano pela última vez.';
$string['reasonforchanges'] = 'Motivo do ajuste solicitado';
$string['requestchanges'] = 'Solicitar ajustes';
$string['resetfielddescription'] = 'Restaurar texto padrão';
$string['saved'] = 'Salvo';
$string['saving'] = 'Salvando...';
$string['schedule'] = 'Cronograma de Atividades e Pontuação';
$string['sectionnavigation'] = 'Navegação por seções';
$string['sectionstatecomplete'] = 'Seção completa';
$string['sectionstateempty'] = 'Seção vazia';
$string['sectionstatepartial'] = 'Seção parcialmente preenchida';
$string['stage'] = 'Etapa';
$string['stagecount'] = 'Número de etapas';
$string['stagecounthelp'] = 'Em quantas etapas independentes de 100 pontos este plano é dividido. Deixe em 1 para uma única etapa de avaliação contínua — nenhuma seleção de etapa aparece em lugar nenhum nesse caso.';
$string['stagen'] = 'Etapa {$a}';
$string['stageoutofrange'] = '(fora do intervalo de etapas atual)';
$string['status_approved'] = 'Aprovado';
$string['status_changesrequested'] = 'Ajustes solicitados';
$string['status_draft'] = 'Rascunho';
$string['status_submitted'] = 'Enviado para revisão';
$string['structuraleditblocked'] = 'Campos estruturais não podem ser editados enquanto este plano aguarda revisão.';
$string['studentinstructions'] = 'Orientações aos estudantes';
$string['studentinstructionshelp'] = 'Explique ao estudante o que fazer e como concluir esta atividade.';
$string['studentinstructionshelpfull'] = 'Escreva um enunciado detalhado, claro e objetivo, conversando diretamente com o estudante. O texto deve conter obrigatoriamente: o objetivo da atividade; a conexão explícita com os objetivos e habilidades trabalhados na aula; o comando da tarefa, o que fazer, de forma precisa e, se possível, em etapas; e a indicação de quais materiais de estudo desta aula servem de base para a resolução.';
$string['studyprogram'] = 'Curso';
$string['submitforreview'] = 'Enviar para revisão';
$string['supplementarymaterial'] = 'Bibliografia — Material complementar';
$string['supplementarymaterialhelp'] = 'Liste leituras e materiais opcionais para aprofundamento.';
$string['supplementarymaterialhelpfull'] = 'Liste, em tópicos, os materiais complementares para aprofundar a compreensão dos conteúdos desta aula — artigos, livros, imagens, vídeos etc. Verifique a licença quando não forem materiais autorais, e informe o recurso do AVA usado para cada item.';
$string['supportmaterial'] = 'Bibliografia — Material de apoio';
$string['supportmaterialhelp'] = 'Liste as leituras e os materiais obrigatórios desta semana.';
$string['supportmaterialhelpfull'] = 'Liste, em tópicos, os materiais que servem de base para esta aula — preferencialmente materiais autorais; quando não forem, verifique a licença antes de usar. Para cada item, informe o recurso do AVA usado (página, PDF, vídeo) e o link ou local de acesso. Respeite as normas ABNT nas referências e a Lei de Direitos Autorais (9.610/98) e a lei antiplágio (10.695/2003).';
$string['syllabus:addinstance'] = 'Adicionar um novo plano de disciplina';
$string['syllabus:review'] = 'Revisar e aprovar planos de disciplina';
$string['syllabus:submit'] = 'Submeter plano de disciplina para revisão';
$string['syllabus:view'] = 'Visualizar um plano de disciplina';
$string['syllabus:viewtutorview'] = 'Visualizar plano de tutoria';
$string['syncmeeting'] = 'Encontro Síncrono';
$string['syncmeetingdate'] = 'Data e horário do encontro';
$string['syncmeetinghelp'] = 'Data, horário, link e tema do encontro ao vivo desta semana, se houver.';
$string['syncmeetinghelpfull'] = 'Preencha a data, o horário e o link de acesso do encontro ao vivo desta semana, e um breve tema resumindo o que será abordado — algo como um convite direto ao estudante. Depois de realizado o encontro, a gravação também deve ser disponibilizada no AVA, no mesmo local. Lembre-se da Instrução Normativa DEaD 10/2021: são exigidas no mínimo 2 atividades síncronas (ou presenciais, nos cursos com encontro presencial obrigatório) durante toda a disciplina — nem toda semana precisa ter um encontro síncrono, mas a disciplina como um todo precisa somar pelo menos 2.';
$string['syncmeetinglink'] = 'Link de acesso';
$string['syncmeetingtopic'] = 'Tema';
$string['tabfullplan'] = 'Plano completo';
$string['tabstudentplan'] = 'Plano do Estudante';
$string['tabtutorplan'] = 'Plano de Tutoria';
$string['teacher'] = 'Professor(a)';
$string['totalsmatch'] = 'Bate';
$string['totalsmismatch'] = 'Não bate';
$string['totalworkload'] = 'Carga horária Total (horas)';
$string['tutorguidance'] = 'Acompanhamento de Tutoria';
$string['tutorguidancehelp'] = 'Oriente o tutor sobre como apoiar os estudantes nesta atividade.';
$string['tutorguidancehelpfull'] = 'Informe como o tutor deve acompanhar esta atividade — por exemplo, a análise do texto ou postagem enviada, a verificação de conclusão por unidade/módulo, e a forma de lançar a pontuação de cada atividade.';
$string['typechat'] = 'Chat';
$string['typeforum'] = 'Fórum';
$string['typegame'] = 'Jogo';
$string['typeother'] = 'Outro';
$string['typepresential'] = 'Presencial';
$string['typequestionnaire'] = 'Questionário';
$string['typequiz'] = 'Quiz';
$string['typetask'] = 'Tarefa';
$string['unpublish'] = 'Despublicar';
$string['viewmodelguidance'] = 'Ver orientações';
$string['visibilitymanaged'] = 'A disponibilidade não é definida manualmente. A atividade permanece oculta enquanto o plano está em rascunho ou em revisão, e é exibida a estudantes e tutores automaticamente assim que a coordenação aprova.';
$string['week'] = 'Aula';
$string['weekduration'] = 'Carga horária (horas)';
$string['weekenddate'] = 'Data de término';
$string['weekplanning'] = 'Carga horária e período da aula';
$string['weekplanninghelp'] = 'Defina a carga horária e o período desta aula de acordo com a carga horária total e o calendário da disciplina.';
$string['weekplanninghelpfull'] = 'Carga horária: estipule quantas horas serão necessárias para esta aula, considerando a carga horária total da disciplina e a quantidade de materiais e atividades que serão disponibilizados — a soma das cargas horárias de todas as aulas deve fechar com a Carga horária Total da Caracterização. Leve em conta o tempo real que o estudante precisa para ler, assistir a vídeos, responder ao questionário e participar do encontro síncrono, se houver. Período da aula: informe a data de início e fim de acordo com o cronograma do curso para esta semana.';
$string['weeks'] = 'Aulas';
$string['weekstartdate'] = 'Data de início';
$string['weektitle'] = 'Título da aula';
