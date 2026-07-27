# 📖 Como Usar

1. Adicione uma atividade **Syllabus** a um curso. Ela permanece oculta para estudantes e
   tutores até que um plano seja aprovado.
2. Como professor, preencha os campos do plano — ementa, objetivos, conteúdos, metodologia,
   referências, critérios de avaliação — cada um salvando automaticamente enquanto você digita,
   com um indicador "salvando.../salvo" ao lado do campo.
3. Adicione aulas e, dentro de cada aula, atividades (título, tipo, categoria, datas, pontuação)
   pela mesma estrutura editável — sem etapa de salvamento separada, sem recarregar a página.
4. Quando o plano estiver completo, **submeta** para revisão da coordenação. Campos estruturais
   (detalhes de aula e atividade) travam para o autor enquanto o plano está `submitted`; o
   conteúdo narrativo continua editável.
5. Quem possui a capability de revisão (coordenação) abre o plano e **aprova** ou **solicita
   ajustes** com uma justificativa. Aprovar torna a atividade visível no curso pela primeira
   vez; solicitar ajustes devolve o controle total ao professor.
6. Após uma primeira aprovação, editar conteúdo narrativo nunca reabre revisão. Editar um campo
   estrutural (título/carga horária/período de uma aula, ou tipo/categoria/datas/pontuação de
   uma atividade) regride imediatamente o plano para `submitted`, para que a coordenação saiba
   que há uma revisão pendente — sem ocultar a atividade nem reverter o que estudantes/tutores
   já veem.
7. Estudantes e tutores veem a atividade **Syllabus** resultante uma vez aprovada, cada um em
   sua própria aba: a aba do estudante omite gabaritos e notas de acompanhamento de tutoria; a
   aba do tutor as inclui.
8. Administradores do site gerenciam os três modelos de Custom Fields (`plan`, `week`,
   `activity`) em **Administração do site > Plugins > Módulos de atividade > Syllabus**.

## Quais edições reabrem a revisão, e quais nunca reabrem

Depois que um plano foi aprovado ao menos uma vez, editá-lo se comporta de forma diferente
dependendo do que mudou.

**Sempre reabre a revisão** — regride o plano para `submitted` para que a coordenação saiba
que precisa olhar de novo, mas nunca esconde a atividade nem reverte o que estudantes/tutores
já veem:

* Editar o título, a carga horária, a data de início/fim, os detalhes do encontro síncrono ou
  a etapa de uma aula
* Editar o título, tipo, categoria, datas ou pontuação de uma atividade
* Excluir uma aula
* Excluir uma atividade
* Editar o bloco de Avaliação Final do plano (título, tipo, datas, pontos)

**Nunca reabre a revisão** — editável livremente em qualquer status, inclusive com uma revisão
pendente:

* Qualquer campo narrativo: ementa, objetivos, conteúdos, metodologia, referências, critérios
  de avaliação, acompanhamento de tutoria, e o restante do conteúdo de Custom Fields
* Os campos de Caracterização (período letivo, período do curso, carga horária total) e o link
  do vídeo de apresentação do professor/curso
* O número de etapas de pontuação e como elas se combinam

**Despublicar continua disponível o tempo todo.** A ação **Despublicar** (voltar o plano para
rascunho e escondê-lo de novo) funciona sempre que a atividade estiver atualmente visível —
inclusive durante uma janela de revisão reaberta — não só a partir do status `approved`
isolado. É o único caminho suportado para esconder um plano já publicado; alternar a
configuração "Disponibilidade" da própria atividade não tem efeito, já que esse campo vem
permanentemente travado numa instância de Syllabus.
