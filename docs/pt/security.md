# 🔐 Segurança e Conformidade

* Controle de acesso baseado em capability: `mod/syllabus:view`, `mod/syllabus:submit`,
  `mod/syllabus:review` e `mod/syllabus:viewtutorview` controlam cada ação e visão por papel
* Todo web service resolve o `cmid` recebido, deriva o contexto real do módulo do curso, e
  chama `validate_context()` antes de qualquer checagem de capability — nunca opera sobre um id
  isolado sem amarrá-lo ao seu próprio curso, verificado por uma suíte dedicada de isolamento
  entre instâncias (`tests/cross_instance_security_test.php`)
* Web services são consumidos via `core/ajax`, cujo transporte já inclui e valida a chave de
  sessão automaticamente
* Um usuário não pode aprovar ou solicitar ajustes num plano que ele mesmo submeteu, mesmo
  possuindo a capability de revisão — verificado no servidor em `plan_state_manager::approve()`,
  nunca deixado apenas para a interface
* Guards de workflow (status errado para uma transição, autoaprovação) levantam um
  `moodle_exception` traduzido, nunca um `coding_exception` — são resultados de regra de
  negócio que uma ação normal do usuário pode disparar, não erros de programador
* O acesso de tutor/estudante ao conteúdo é controlado, em `view.php`, pelo próprio flag
  `visible` do módulo de curso — alterado só por `plan_state_manager::approve()`/`unpublish()`
  — em vez do status literal do plano, para que uma edição estrutural que reabre a revisão de
  um plano aprovado nunca bloqueie conteúdo que tutores/estudantes já veem. A checagem continua
  aplicada no servidor, independente da filtragem de atividades ocultas da página do curso do
  Moodle, que por si só não bloqueia acesso direto de um papel com
  `moodle/course:viewhiddenactivities`
* O conteúdo narrativo é dado da Custom Fields API, renderizado via `format_text()` — nunca
  impresso cru
* Compatível com a External API do Moodle
* API de Privacidade totalmente implementada: o conteúdo do plano é o registro pedagógico do
  próprio curso, não dado pessoal — apenas as três referências de ator do workflow (submetido/
  revisado/despublicado por) são exportadas/anonimizadas por usuário, já que o plano em si é
  conteúdo institucional compartilhado e contínuo que precisa sobreviver a uma solicitação de
  exclusão de dados, não uma submissão por usuário
