# 🛠️ Instalação e Configuração

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `syllabus` (se necessário).
   Caminho final:
   `seu-moodle/mod/syllabus/`
4. Acesse **Administração do site > Notificações** para concluir a instalação. Isso também
   semeia os Custom Fields padrão das áreas `plan`, `week` e `activity`, traduzidos para o
   idioma de quem está instalando.
5. Atribua a capability de revisão (`mod/syllabus:review`) ao papel que representa a
   coordenação na sua instituição — tipicamente adicionado no nível de categoria de curso — e a
   capability de visão do tutor (`mod/syllabus:viewtutorview`) ao seu papel de tutor.
6. Adicione uma atividade **Syllabus** a um curso.

Os Custom Fields narrativos podem ser revisados ou estendidos por área em **Administração do
site > Plugins > Módulos de atividade > Syllabus**, em **Gerenciar campos de plano/aula/
atividade**, conforme explicado na seção [Como Usar](#usage) logo abaixo.
